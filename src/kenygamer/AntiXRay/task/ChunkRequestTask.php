<?php

declare(strict_types=1);


/**
 *                 _   ___   _______             
 *     /\         | | (_) \ / /  __ \            
 *    /  \   _ __ | |_ _ \ V /| |__) |__ _ _   _ 
 *   / /\ \ | '_ \| __| | > < |  _  // _` | | | |
 *  / ____ \| | | | |_| |/ . \| | \ \ (_| | |_| |
 * /_/    \_\_| |_|\__|_/_/ \_\_|  \_\__,_|\__, |
 *                                          __/ |
 *                                         |___/ 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author kenygamer
 * @link github.com/kenygamer
 * @copyright
 * @license GNU General Public License v3.0
 */

namespace kenygamer\AntiXRay\task;

use pocketmine\level\format\Chunk;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;

use kenygamer\AntiXRay\Main;
use kenygamer\AntiXRay\EventListener;

/**
 * @package kenygamer\AntiXRay\task
 * @class ChunkRequestTask
 */
final class ChunkRequestTask extends AsyncTask{
	private const ALL_SIDES = [
		Vector3::SIDE_DOWN, Vector3::SIDE_UP, Vector3::SIDE_NORTH, Vector3::SIDE_SOUTH, Vector3::SIDE_WEST, Vector3::SIDE_EAST
	];
	
	/** @var string */
	private $username;
	/** @var int */
	private $levelId;
	/** @var int */
	private $playerSubChunk;
	/** @var int */
	private $playerX;
	/** @var int */
	private $playerZ;
	/** @var int */
	private $playerY;
	/** @var int */
	private $maxDist;
	/** @var int */
	private $playerChunkX;
	/** @var int */
	private $playerChunkZ;
	/** @var int */
	private $heightMin;
	/** @var int */
	private $heightMax;
	/** @var bool */
	private $hideOres;
	/** @var bool */
	private $hideChunks;
	
	/** @var int */
	private $chunkX;
	/** @var int */
	private $chunkZ;
	/** @var int */
	private $subChunkCount;
	/** @var bool */
	private $cacheEnabled;
	/** @var string */
	private $payload;
	/** @var string int[] serialized */
	private $usedBlobHashes;
	/** @var int */
	private $compressionLevel;
	
	/**
	 * ChunkRequestTask constructor.
	 *
	 * @param int $chunkX
	 * @param int $chunkZ
	 * @param int $levelId
	 * @param string $username
	 * @param int $heightMin
	 * @param int $heightMax
	 * @param bool $hideOres
	 * @param bool $hideChunks
	 * @param int $maxDist
	 */
	public function __construct(int $chunkX, int $chunkZ, int $levelId, string $username, int $heightMin, int $heightMax, bool $hideOres, bool $hideChunks, int $maxDist){
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->maxDist = $maxDist;
		$this->cacheEnabled = false;
		$this->usedBlobHashes = [];
		
		$level = Server::getInstance()->getLevel($levelId);
		if($level === null){
			throw new \AsumptionFailedError("Level should never be null");
		}
		
		$this->compressionLevel = $level->getServer()->networkCompressionLevel;
		$chunk = $level->getChunk($chunkX, $chunkZ, false);
		$this->subChunkCount = $chunk->getSubChunkSendCount();
		$this->payload = $chunk->fastSerialize(); //EDITED
		
		$player = Server::getInstance()->getPlayerExact($username);
		if($player === null){
			throw new \AssumptionFailedError("Username should always resolve a player");
		}
		$this->playerY = $player->getY();
		$this->playerChunkX = ($this->playerX = $player->getX()) >> 4;
		$this->playerChunkZ = ($this->playerZ = $player->getZ()) >> 4;
		$this->playerSubChunk = $player->getY() >> 4;
		$this->maxDist = $maxDist;
		
		$this->username = $username;
		$this->levelId = $levelId;
		
		$biome = $level->getBiome($chunkX, $chunkZ);
		if($heightMin < 0){
			$this->heightMin = 0;
		}else{
			$this->heightMin = $heightMin;
		}
		if($heightMax < 0){
			$this->heightMax = $biome->getMinElevation();
		}else{
			$this->heightMax = $heightMax;
		}
		$this->hideOres = $hideOres;
		$this->hideChunks = $hideChunks;
	}
	
	/**
	 * Returns whether the position is viewable or not by the player.
	 
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 *
	 * @return bool
	 */
	public function isViewable(int $x, int $y, int $z) : bool{
		$subChunk = $y >> 4;
		if($subChunk < 0 xor $subChunk > 15){
			throw new \OutOfBoundsException("Subchunk must be between 0 and 15");
		}
		$x = ($this->chunkX << 4) + $x;
		$z = ($this->chunkZ << 4) + $z;
		return sqrt(
			(($this->playerX - $x) ** 2) + (($this->playerY - $y) ** 2) + (($this->playerZ - $z) ** 2))	<= $this->maxDist;
		
		if($this->chunkX === $this->playerChunkX && $this->chunkZ === $this->playerChunkZ){
			if($subChunk === $this->playerSubChunk){ //Center
				return true;
			}
			if($subChunk === $this->playerSubChunk - 1){ //Bottom
				
				return true;
		
			}
			if($subChunk === $this->playerSubChunk + 1){ //Top

				
				return true;
			}
		}
		if($subChunk === $this->playerSubChunk){
			if($this->chunkZ === $this->playerChunkZ){
				if($this->chunkX === $this->playerChunkX - 1){ //West
					return true;
				}
				if($this->chunkX === $this->playerChunkX + 1){ //East
					return true;
				}	
				
			}
			if($this->chunkX === $this->playerChunkX){
				if($this->chunkZ === $this->playerChunkZ + 1){ //South
					return true;
				}
				if($this->chunkZ === $this->playerChunkZ - 1){ //North
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * Actions to execute when run.
	 */
	public function onRun() : void{
		$chunk = Chunk::fastDeserialize($this->payload);
		
		for($x = 0; $x < 16; $x++){
			for($y = $this->heightMin; $y < $this->heightMax; $y++){
				for($z = 0; $z < 16; $z++){	
					
					if(!$this->isViewable($x, $y, $z)){
						
						if($this->hideOres){
							$block = $chunk->getBlockId($x, $y, $z);
							if(in_array($block, Main::ORE_BLOCKS)){
								$chunk->setBlockId($x, $y, $z, Block::INVISIBLE_BEDROCK);
								$chunk->setBlockData($x, $y, $z, 0);
							}
						}
						
						if($this->hideChunks){
							$chunk->setBlockId($x, $y, $z, Block::INVISIBLE_BEDROCK);
							$chunk->setBlockData($x, $y, $z, 0);
						}
						
					}
				}
			}
		}
		$payload = $chunk->networkSerialize();
		
		$this->makePacket($payload);
	}
	
	/**
	 * @param string $payload
	 */
	private function makePacket(string $payload) : void{
		if($this->cacheEnabled){
			
			$pk = LevelChunkPacket::withCache($this->chunkX, $this->chunkZ, $this->subChunkCount, unserialize($this->usedBlobHashes), $payload);
		}else{ //Always
			
			$pk = LevelChunkPacket::withoutCache($this->chunkX, $this->chunkZ, $this->subChunkCount, $payload);
		}
		
		$batch = new BatchPacket();
		$batch->addPacket($pk);
		$batch->setCompressionLevel($this->compressionLevel);
		$batch->encode();
		$this->setResult($batch->buffer);
	}
	
	
	/**
	 * Actions to execute on main thread.
	 *
	 * @param Server $server
	 */
	public function onCompletion(Server $server) : void{
		$payload = $this->getResult();
		
		$level = $server->getLevel($this->levelId);
		if($level instanceof Level){
			$level->clearChunkCache($this->chunkX, $this->chunkZ); //We dont use it anyway
		}
		
		$player = $server->getPlayerExact($this->username);
		if($player instanceof Player){
			$batch = new BatchPacket($payload);
			$batch->isEncoded = true;
			EventListener::$sendLevelChunk[$this->username] = true;
			
			$player->directDataPacket($batch);
			
			EventListener::$sendLevelChunk[$this->username] = false;
		}
		
	}
	
}