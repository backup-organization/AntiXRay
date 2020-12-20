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

namespace kenygamer\AntiXRay;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;

use kenygamer\AntiXRay\task\ChunkRequestTask;
use kenygamer\AntiXRay\task\FakeChunkSendTask;

/**
 * @package kenygamer\XRay
 * @class EventListener
 */
final class EventListener implements Listener{
	/** @var Main */
	private $plugin;
	/** @var bool[] */
	public static $sendLevelChunk = [];
	/** @var string */
	private $currentSubChunk = [];
	/** @var Vector3[] */
	private $lastPos = [];
	
	/**
	 * @param Main $plugin
	 */
	public function __construct(Main $plugin){
		$this->plugin = $plugin;
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}
	
	/**
	 * @param EntityTeleportEvent $event
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onEntityTeleport(EntityTeleportEvent $event) : void{
		$entity = $event->getEntity();
		if(!($entity instanceof Player)){
			return;
		}
		$chunk = $entity->getLevel()->getChunkAtPosition($entity->asPosition());
		$from = $event->getFrom();
		$to = $event->getTo();
		if($to->getLevel()->getFolderName() !== $from->getLevel()->getFolderName()){
			unset($this->currentSubChunk[$entity->getName()]);
		}
	}
	
	/**
	 * @param PlayerMoveEvent $event
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onPlayerMove(PlayerMoveEvent $event) : void{
		$player = $event->getPlayer();
		$to = $event->getTo();
		$from = $event->getFrom();
		$toChunk = $player->getLevel()->getChunkAtPosition($to->asPosition());
		$fromChunk = $player->getLevel()->getChunkAtPosition($from->asPosition());
		
		$newSubChunk = $to->getY() >> 4;
		
		if(!isset($this->lastPos[$player->getName()]) || $player->distance($this->lastPos[$player->getName()]) > $this->plugin->maxDist / 2){
			$this->lastPos[$player->getName()] = $player->asVector3();
			$this->currentSubChunk[$player->getName()] = $newSubChunk;
			
			$chunks = $player->getLevel()->getAdjacentChunks($toChunk->getX(), $toChunk->getZ());
			$chunks = $chunks + $player->getLevel()->getAdjacentChunks($fromChunk->getX(), $fromChunk->getZ());
			$chunks[] = $fromChunk;
			$chunks[] = $toChunk;
			foreach($chunks as $chunk){
				$this->updateChunk($chunk->getX(), $chunk->getZ(), $player);
			}
		}
	}
	
	/** 
	 * @param DataPacketSendEvent $event
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) : void{
		$player = $event->getPlayer();
		$pk = $event->getPacket();
		if($player->hasPermission("antixray.bypass")){
			return;
		}
		
		if($pk instanceof BatchPacket){
			$pk->decode();
			foreach($pk->getPackets() as $payload){
				$packet = PacketPool::getPacket($payload);
				$packet->decode();
				
				switch(true){
					
					case ($packet instanceof LevelChunkPacket):
						if(!isset(self::$sendLevelChunk[$player->getName()])){
							self::$sendLevelChunk[$player->getName()] = false;
						}
						if(!self::$sendLevelChunk[$player->getName()]){
							$event->setCancelled();
							$this->updateChunk($packet->getChunkX(), $packet->getChunkZ(), $player);
						}
						break;
					
				}
			}
		}
	}
	
	/**
	 * @param int $chunkX
	 * @param int $chunkZ
	 * @param Player $player
	 */
	private function updateChunk(int $chunkX, int $chunkZ, Player $player) : void{
		$this->plugin->getServer()->getAsyncPool()->submitTask(
			new ChunkRequestTask(
				$chunkX, $chunkZ, $player->getLevel()->getId(), $player->getName()
			)
		);
	}
	
}