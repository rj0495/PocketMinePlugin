<?php

namespace MineBlock\WorldGenerator;

use pocketmine\plugin\PluginBase;
use pocketmine\level\generator\Generator;

class WorldGenerator extends PluginBase{

	public function onLoad(){
		Generator::addGenerator(ApartGN::class, "Apart");
		Generator::addGenerator(IceGN::class, "Ice");
		Generator::addGenerator(LavaGN::class, "Lava");
		Generator::addGenerator(ManySkyBlockGN::class, "ManySkyBlock");
		Generator::addGenerator(ManyTreeGN::class, "ManyTree");
		Generator::addGenerator(MultySkyBlockGN::class, "MultySkyBlock");
		Generator::addGenerator(MultySpecialSkyBlockGN::class, "MultySpecialSkyBlock");
		Generator::addGenerator(NoneGN::class, "None");
		Generator::addGenerator(OreFlatGN::class, "OreFlat");
		Generator::addGenerator(OreTreeFlatGN::class, "OreTreeFlat");
		Generator::addGenerator(SkyBlockGN::class, "SkyBlock");
		Generator::addGenerator(SkyGridGN::class, "SkyGrid");
		Generator::addGenerator(TreeGN::class, "Tree");
		Generator::addGenerator(WaterGN::class, "Water");
		Generator::addGenerator(WhiteWayGN::class, "WhiteWay");
	}
}