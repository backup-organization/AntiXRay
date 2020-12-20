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

use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

/**
 * @package kenygamer\AntiXRay
 * @class Main
 */
final class Main extends PluginBase{
	public const ORE_BLOCKS = [
		Block::COAL_ORE, Block::IRON_ORE, Block::GOLD_ORE,
		Block::LAPIS_ORE, Block::DIAMOND_ORE, Block::EMERALD_ORE
	];

	/** @var EventListener */
	private $listener;

	/** @var int */
	public $heightMin = -1;
	/** @var int */
	public $heightMax = -1;
	/** @var int */
	public $hideOres = false;
	/** @var bool */
	public $hideChunks = true;
	/** @vsar int */
	public $maxDist = 16;
	
	/** @var self|null */
	private static $instance = null;
	
	/**
	 * Called when the plugin loads.
	 */
	public function onLoad() : void{
		self::$instance = $this;
	}
	
	/**
	 * Called when the plugin enables.
	 */
	public function onEnable() : void{
		$this->saveResource("config.yml", true);
		if(!$this->loadConfig()){
			$this->getLogger()->critical("Plugin configuration is not correctly set up. Check the main repository for reference or let the plugin regenerate the default configuration deleting the existing one.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}else{
			$this->listener = new EventListener($this);
		}
	}
	
	/*
	 * @return self|null
	 */
	public static function getInstance() : ?self{
		return self::$instance;
	}
	
	/**
	 * @return EventListener
	 */
	public function getListener() : EventListener{
		return $this->listener;
	}
	
	/**
	 * Loads the plugin configuration.
	 *
	 * @return bool
	 */
	private function loadConfig() : bool{
		$this->heightMin = $this->getConfigKey("height-min", "int");
		if(
			($this->heightMin = $this->getConfigKey("height-min", "int")) === null ||
			($this->heightMax = $this->getConfigKey("height-max", "int")) === null ||
			($this->hideOres = $this->getConfigKey("hide-ores", "bool")) === null ||
			($this->hideChunks = $this->getConfigKey("hide-chunks", "bool")) === null /*||
			$this->maxDist = $this->getConfigKey("max-dist", "int")) === null*/
		){
			return false;
		}
		$this->maxDist = 16;
		return true;
	}
	
	/**
	 * Get a config key. 
	 *
	 * @param string $key4
	 * @param string $expectedType
	 *
	 * @return mixed|null null if not of expected type
	 */
	public function getConfigKey(string $key, string $expectedType = ""){
	    $value = $this->getConfig()->getNested($key);
	    $expected = $expectedType === "";
	    switch($expectedType){
	    	case "str":
	    	case "string":
	    	    $expected = is_string($value);
	    	    break;
	    	case "bool":
	    	case "boolean":
	    	    $expected = is_bool($value);
	    	    break;
	    	case "int":
	    	case "integer":
	    	    $expected = is_int($value);
	    	    break;
	    	case "float":
	    	    $expected = is_float($value) || is_int($value);
	    	    break;
	    	case "arr":
	    	case "array":
	    	    $expected = is_array($value);
	    	    break;
	    }
	    if(!$expected){
	    	$this->getLogger()->warning("Config key `" . $key . "` not of expected type " . $expectedType);
	    	return null;
	    }
	    return $value;
	}
	
}