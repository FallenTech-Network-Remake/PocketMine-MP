<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\light;

use pocketmine\world\format\Chunk;
use pocketmine\world\format\HeightArray;
use pocketmine\world\format\LightArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\utils\SubChunkExplorer;
use pocketmine\world\utils\SubChunkExplorerStatus;
use pocketmine\world\World;
use function assert;
use function max;
use function min;

class SkyLightUpdate extends LightUpdate{
	/** @var array<int, array{int, int, int, \WeakReference<Chunk>}> */
	private array $brightenedColumns = [];

	/**
	 * @param int[]  $lightFilters
	 * @param true[] $directSkyLightBlockers
	 * @phpstan-param array<int, int>  $lightFilters
	 * @phpstan-param array<int, true> $directSkyLightBlockers
	 */
	public function __construct(
		SubChunkExplorer $subChunkExplorer,
		array $lightFilters,
		private array $directSkyLightBlockers
	){
		parent::__construct($subChunkExplorer, $lightFilters);
	}

	protected function getCurrentLightArray() : LightArray{
		return $this->subChunkExplorer->currentSubChunk->getBlockSkyLightArray();
	}

	protected function getEffectiveLight(int $x, int $y, int $z) : int{
		if($y >= World::Y_MAX){
			$this->subChunkExplorer->invalidate();
			return 15;
		}
		return parent::getEffectiveLight($x, $y, $z);
	}

	public function recalculateNode(int $x, int $y, int $z) : void{
		if($this->subChunkExplorer->moveTo($x, $y, $z) === SubChunkExplorerStatus::INVALID){
			return;
		}
		$chunk = $this->subChunkExplorer->currentChunk;

		$oldHeightMap = $chunk->getHeightMap($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		$source = $this->subChunkExplorer->currentSubChunk->getBlockStateId($x & SubChunk::COORD_MASK, $y & SubChunk::COORD_MASK, $z & SubChunk::COORD_MASK);

		$yPlusOne = $y + 1;

		if($yPlusOne === $oldHeightMap){ //Block changed directly beneath the heightmap. Check if a block was removed or changed to a different light-filter.
			$newHeightMap = self::recalculateHeightMapColumn($chunk, $x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK, $this->directSkyLightBlockers);
			$chunk->setHeightMap($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK, $newHeightMap);
		}elseif($yPlusOne > $oldHeightMap){ //Block changed above the heightmap.
			if(isset($this->directSkyLightBlockers[$source])){
				$chunk->setHeightMap($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK, $yPlusOne);
				$newHeightMap = $yPlusOne;
			}else{ //Block changed which has no effect on direct sky light, for example placing or removing glass.
				return;
			}
		}else{ //Block changed below heightmap
			$newHeightMap = $oldHeightMap;
		}

		if($newHeightMap >= $oldHeightMap){
			for($i = $y - 1; $i >= $oldHeightMap; --$i){
				$this->setAndUpdateLight($x, $i, $z, 0); //Remove all light beneath, adjacent recalculation will handle the rest.
			}

			//recalculate light for the placed block from its surroundings - this avoids having to check effective light during propagation
			$this->setAndUpdateLight($x, $y, $z, max(0, $this->getHighestAdjacentLight($x, $y, $z) - ($this->lightFilters[$source] ?? self::BASE_LIGHT_FILTER)));
		}else{ //Heightmap decrease, block changed or removed, add sky light
			assert($chunk !== null);
			$this->brightenedColumns[World::chunkHash($x, $z)] = [$x, $z, $newHeightMap, \WeakReference::create($chunk)];
			for($i = $y; $i >= $newHeightMap; --$i){
				$this->setAndUpdateLight($x, $i, $z, 15);
			}
		}
	}

	/** @return array{int, array<int, array{int, int, Chunk}>}|null */
	private function findEmptyRegion() : ?array{
		if($this->brightenedColumns === [] || $this->subChunkExplorer->getMinSubChunkIndex() !== Chunk::MIN_SUBCHUNK_INDEX){
			return null;
		}
		foreach($this->updateNodes as [$x, $y, $z, $level]){
			$column = $this->brightenedColumns[World::chunkHash($x, $z)] ?? null;
			if($level !== 15 || $column === null || $y < $column[2]){
				return null;
			}
		}
		$explorer = $this->subChunkExplorer->withMinSubChunkIndex(Chunk::MIN_SUBCHUNK_INDEX);
		$wideShadow = false;
		foreach($this->brightenedColumns as [$x, $z, $height, $expectedChunk]){
			if($explorer->moveTo($x, World::Y_MIN, $z) === SubChunkExplorerStatus::INVALID){
				return null;
			}
			$chunk = $explorer->currentChunk;
			assert($chunk !== null);
			if($chunk !== $expectedChunk->get() || $chunk->getHeightMap($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK) !== $height){
				return null;
			}
			$subChunk = $explorer->currentSubChunk;
			assert($subChunk !== null);
			if($subChunk->getBlockSkyLightArray()->get($x & Chunk::COORD_MASK, 0, $z & Chunk::COORD_MASK) < 14){
				$wideShadow = true;
			}
		}
		if(!$wideShadow){
			return null;
		}

		$chunks = [];
		foreach($this->brightenedColumns as [$x, $z]){
			$chunkX = $x >> Chunk::COORD_BIT_SIZE;
			$chunkZ = $z >> Chunk::COORD_BIT_SIZE;
			for($cx = $chunkX - 1; $cx <= $chunkX + 1; ++$cx){
				for($cz = $chunkZ - 1; $cz <= $chunkZ + 1; ++$cz){
					$hash = World::chunkHash($cx, $cz);
					if(isset($chunks[$hash])){
						continue;
					}
					if($explorer->moveToChunk($cx, 0, $cz) === SubChunkExplorerStatus::INVALID){
						return null;
					}
					$chunk = $explorer->currentChunk;
					assert($chunk !== null);
					$chunks[$hash] = [$cx, $cz, $chunk];
				}
			}
		}

		$lowestTerrain = World::Y_MAX;
		$checked = [];
		foreach($chunks as [$chunkX, $chunkZ]){
			for($cx = $chunkX - 1; $cx <= $chunkX + 1; ++$cx){
				for($cz = $chunkZ - 1; $cz <= $chunkZ + 1; ++$cz){
					$hash = World::chunkHash($cx, $cz);
					if(isset($checked[$hash])){
						continue;
					}
					$checked[$hash] = true;
					if($explorer->moveToChunk($cx, 0, $cz) === SubChunkExplorerStatus::INVALID){
						return null;
					}
					$chunk = $explorer->currentChunk;
					assert($chunk !== null);
					if($chunk->isLightPopulated() !== true){
						return null;
					}
					$emptyState = $chunk->getSubChunk(Chunk::MIN_SUBCHUNK_INDEX)->getEmptyBlockId();
					if(($this->lightFilters[$emptyState] ?? self::BASE_LIGHT_FILTER) !== self::BASE_LIGHT_FILTER){
						return null;
					}
					foreach($chunk->getSubChunks() as $y => $subChunk){
						if(!$subChunk->isEmptyFast()){
							if($y < Chunk::MIN_SUBCHUNK_INDEX + 5){
								return null;
							}
							$lowestTerrain = min($lowestTerrain, $y << SubChunk::COORD_BIT_SIZE);
							break;
						}
					}
				}
			}
		}

		// Below this buffer, nearby terrain cannot contribute attenuated light from above.
		$bottom = $lowestTerrain - SubChunk::EDGE_LENGTH;
		if($bottom < World::Y_MIN + 4 * SubChunk::EDGE_LENGTH){
			return null;
		}
		foreach($this->brightenedColumns as [$x, $z]){
			$chunk = $chunks[World::chunkHash($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)][2];
			if($chunk->getHeightMap($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK) > $bottom){
				continue;
			}
			for($sy = Chunk::MIN_SUBCHUNK_INDEX; $sy < ($bottom >> SubChunk::COORD_BIT_SIZE); ++$sy){
				$light = $chunk->getSubChunk($sy)->getBlockSkyLightArray();
				for($y = 0; $y < SubChunk::EDGE_LENGTH; ++$y){
					if($light->get($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK) === 15){
						return null;
					}
				}
			}
		}
		foreach($this->updateNodes as [, $y]){
			if($y < $bottom){
				return [$bottom, $chunks];
			}
		}
		return null;
	}

	public function execute() : int{
		$region = $this->findEmptyRegion();
		if($region === null){
			return parent::execute();
		}
		[$bottom, $chunks] = $region;
		$columns = $this->getEmptyColumnLight($bottom, $chunks);
		if(!$this->canBrightenEmptyColumns($bottom, $chunks, $columns)){
			return parent::execute();
		}
		$nodes = $this->updateNodes;
		foreach($this->updateNodes as $hash => [, $y]){
			if($y < $bottom){
				unset($this->updateNodes[$hash]);
			}
		}
		$explorer = $this->subChunkExplorer;
		$this->subChunkExplorer = $explorer->withMinSubChunkIndex($bottom >> SubChunk::COORD_BIT_SIZE);
		try{
			$touched = parent::execute();
		}finally{
			$this->subChunkExplorer = $explorer;
			$this->updateNodes = $nodes;
		}

		$this->brightenEmptyColumns($bottom, $chunks, $columns);
		return $touched;
	}

	/**
	 * @param array<int, array{int, int, Chunk}> $chunks
	 * @return array<int, array<int, int>>
	 */
	private function getEmptyColumnLight(int $bottom, array $chunks) : array{
		/** @var array<int, int> $levels */
		$levels = [];
		/** @var \SplQueue<array{int, int}> $queue */
		$queue = new \SplQueue();
		foreach($this->brightenedColumns as [$x, $z]){
			$chunk = $chunks[World::chunkHash($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)][2];
			if($chunk->getHeightMap($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK) <= $bottom){
				$levels[World::chunkHash($x, $z)] = 15;
				$queue->enqueue([$x, $z]);
			}
		}
		/** @var array<int, array<int, int>> $columns */
		$columns = [];
		while(!$queue->isEmpty()){
			[$x, $z] = $queue->dequeue();
			$level = $levels[World::chunkHash($x, $z)];
			$columns[World::chunkHash($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)][(($x & Chunk::COORD_MASK) << Chunk::COORD_BIT_SIZE) | ($z & Chunk::COORD_MASK)] = $level;
			if($level <= 1){
				continue;
			}
			foreach([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dz]){
				$hash = World::chunkHash($x + $dx, $z + $dz);
				if(($levels[$hash] ?? 0) < $level - 1){
					$levels[$hash] = $level - 1;
					$queue->enqueue([$x + $dx, $z + $dz]);
				}
			}
		}

		return $columns;
	}

	/**
	 * @param array<int, array{int, int, Chunk}> $chunks
	 * @param array<int, array<int, int>>        $columns
	 */
	private function canBrightenEmptyColumns(int $bottom, array $chunks, array $columns) : bool{
		foreach($columns as $hash => $chunkColumns){
			[$chunkX, $chunkZ, $chunk] = $chunks[$hash];
			foreach($chunkColumns as $column => $level){
				if($level <= 1){
					continue;
				}
				$x = $column >> Chunk::COORD_BIT_SIZE;
				$z = $column & Chunk::COORD_MASK;
				$neighbours = [];
				foreach([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dz]){
					$nx = ($chunkX << Chunk::COORD_BIT_SIZE) + $x + $dx;
					$nz = ($chunkZ << Chunk::COORD_BIT_SIZE) + $z + $dz;
					$neighbourHash = World::chunkHash($nx >> Chunk::COORD_BIT_SIZE, $nz >> Chunk::COORD_BIT_SIZE);
					$neighbourColumn = (($nx & Chunk::COORD_MASK) << Chunk::COORD_BIT_SIZE) | ($nz & Chunk::COORD_MASK);
					if(($columns[$neighbourHash][$neighbourColumn] ?? 0) === $level - 1){
						$neighbours[] = [$chunks[$neighbourHash][2], $nx & Chunk::COORD_MASK, $nz & Chunk::COORD_MASK];
					}
				}
				for($sy = Chunk::MIN_SUBCHUNK_INDEX; $sy <= ($bottom >> SubChunk::COORD_BIT_SIZE); ++$sy){
					$light = $chunk->getSubChunk($sy)->getBlockSkyLightArray();
					$limit = ($sy === ($bottom >> SubChunk::COORD_BIT_SIZE)) ? 1 : SubChunk::EDGE_LENGTH;
					foreach($neighbours as [$neighbour, $nx, $nz]){
						$neighbourLight = $neighbour->getSubChunk($sy)->getBlockSkyLightArray();
						for($y = 0; $y < $limit; ++$y){
							// An unchanged bright cell must not hide a darker cell from the ordinary flood fill.
							if($light->get($x, $y, $z) >= $level && $neighbourLight->get($nx, $y, $nz) < $level - 1){
								return false;
							}
						}
					}
				}
			}
		}
		return true;
	}

	/**
	 * @param array<int, array{int, int, Chunk}> $chunks
	 * @param array<int, array<int, int>>        $columns
	 */
	private function brightenEmptyColumns(int $bottom, array $chunks, array $columns) : void{
		// Only add the new columns' contribution; preserve any brighter preexisting light.
		foreach($columns as $hash => $chunkColumns){
			$chunk = $chunks[$hash][2];
			for($sy = Chunk::MIN_SUBCHUNK_INDEX; $sy < ($bottom >> SubChunk::COORD_BIT_SIZE); ++$sy){
				$light = $chunk->getSubChunk($sy)->getBlockSkyLightArray();
				foreach($chunkColumns as $column => $level){
					$x = $column >> Chunk::COORD_BIT_SIZE;
					$z = $column & Chunk::COORD_MASK;
					for($y = 0; $y < SubChunk::EDGE_LENGTH; ++$y){
						if($light->get($x, $y, $z) < $level){
							$light->set($x, $y, $z, $level);
						}
					}
				}
			}
		}
	}

	public function recalculateChunk(int $chunkX, int $chunkZ) : int{
		if($this->subChunkExplorer->moveToChunk($chunkX, 0, $chunkZ) === SubChunkExplorerStatus::INVALID){
			throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not exist");
		}
		$chunk = $this->subChunkExplorer->currentChunk;

		$newHeightMap = self::recalculateHeightMap($chunk, $this->directSkyLightBlockers);
		$chunk->setHeightMapArray($newHeightMap->getValues());

		//setAndUpdateLight() won't bother propagating from nodes that are already what we want to change them to, so we
		//have to avoid filling full light for any subchunk that contains a heightmap Y coordinate
		$highestHeightMapPlusOne = max($chunk->getHeightMapArray()) + 1;
		$lowestClearSubChunk = ($highestHeightMapPlusOne >> SubChunk::COORD_BIT_SIZE) + (($highestHeightMapPlusOne & SubChunk::COORD_MASK) !== 0 ? 1 : 0);
		for($y = Chunk::MIN_SUBCHUNK_INDEX; $y < $lowestClearSubChunk && $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
			$chunk->getSubChunk($y)->setBlockSkyLightArray(LightArray::fill(0));
		}
		for($y = $lowestClearSubChunk; $y <= Chunk::MAX_SUBCHUNK_INDEX; $y++){
			$chunk->getSubChunk($y)->setBlockSkyLightArray(LightArray::fill(15));
		}

		$lightSources = 0;

		$baseX = $chunkX << Chunk::COORD_BIT_SIZE;
		$baseZ = $chunkZ << Chunk::COORD_BIT_SIZE;
		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$currentHeight = $chunk->getHeightMap($x, $z);

				if($currentHeight === World::Y_MAX){
					//this column has a light-filtering block in the top cell - make sure it's lit from above the world
					//light from above the world bounds will not be checked during propagation
					$y = $currentHeight - 1;
					if($this->subChunkExplorer->moveTo($x + $baseX, $y, $z + $baseZ) !== SubChunkExplorerStatus::INVALID){
						$block = $this->subChunkExplorer->currentSubChunk->getBlockStateId($x, $y & SubChunk::COORD_MASK, $z);
						$this->setAndUpdateLight($x + $baseX, $y, $z + $baseZ, max(0, 15 - ($this->lightFilters[$block] ?? self::BASE_LIGHT_FILTER)));
					}
				}else{
					$maxAdjacentHeight = World::Y_MIN;
					if($x !== 0){
						$maxAdjacentHeight = max($maxAdjacentHeight, $chunk->getHeightMap($x - 1, $z));
					}
					if($x !== 15){
						$maxAdjacentHeight = max($maxAdjacentHeight, $chunk->getHeightMap($x + 1, $z));
					}
					if($z !== 0){
						$maxAdjacentHeight = max($maxAdjacentHeight, $chunk->getHeightMap($x, $z - 1));
					}
					if($z !== 15){
						$maxAdjacentHeight = max($maxAdjacentHeight, $chunk->getHeightMap($x, $z + 1));
					}

					/*
					 * We skip the top two blocks between current height and max adjacent (if there's a difference) because:
					 * - the block next to the highest adjacent will do nothing during propagation (it's surrounded by 15s)
					 * - the block below that block will do the same as the node in the highest adjacent
					 * NOTE: If block opacity becomes direction-aware in the future, the second point will become invalid.
					 */
					$nodeColumnEnd = max($currentHeight, $maxAdjacentHeight - 2);
					for($y = $currentHeight; $y <= $nodeColumnEnd; $y++){
						$this->setAndUpdateLight($x + $baseX, $y, $z + $baseZ, 15);
						$lightSources++;
					}
					for($y = $nodeColumnEnd + 1, $yMax = $lowestClearSubChunk * SubChunk::EDGE_LENGTH; $y < $yMax; $y++){
						if($this->subChunkExplorer->moveTo($x + $baseX, $y, $z + $baseZ) !== SubChunkExplorerStatus::INVALID){
							$this->getCurrentLightArray()->set($x, $y & SubChunk::COORD_MASK, $z, 15);
						}
					}
				}
			}
		}

		return $lightSources;
	}

	/**
	 * Recalculates the heightmap for the whole chunk.
	 *
	 * @param true[] $directSkyLightBlockers
	 * @phpstan-param array<int, true> $directSkyLightBlockers
	 */
	private static function recalculateHeightMap(Chunk $chunk, array $directSkyLightBlockers) : HeightArray{
		$maxSubChunkY = Chunk::MAX_SUBCHUNK_INDEX;
		for(; $maxSubChunkY >= Chunk::MIN_SUBCHUNK_INDEX; $maxSubChunkY--){
			if(!$chunk->getSubChunk($maxSubChunkY)->isEmptyFast()){
				break;
			}
		}
		$result = HeightArray::fill(World::Y_MIN);
		if($maxSubChunkY < Chunk::MIN_SUBCHUNK_INDEX){ //whole column is definitely empty
			return $result;
		}

		for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
			for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
				$y = null;
				for($subChunkY = $maxSubChunkY; $subChunkY >= Chunk::MIN_SUBCHUNK_INDEX; $subChunkY--){
					$subHighestBlockY = $chunk->getSubChunk($subChunkY)->getHighestBlockAt($x, $z);
					if($subHighestBlockY !== null){
						$y = ($subChunkY * SubChunk::EDGE_LENGTH) + $subHighestBlockY;
						break;
					}
				}

				if($y === null){ //no blocks in the column
					$result->set($x, $z, World::Y_MIN);
				}else{
					for(; $y >= World::Y_MIN; --$y){
						if(isset($directSkyLightBlockers[$chunk->getBlockStateId($x, $y, $z)])){
							$result->set($x, $z, $y + 1);
							break;
						}
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Recalculates the heightmap for the block column at the specified X/Z chunk coordinates
	 *
	 * @param int    $x                      0-15
	 * @param int    $z                      0-15
	 * @param true[] $directSkyLightBlockers
	 * @phpstan-param array<int, true> $directSkyLightBlockers
	 *
	 * @return int New calculated heightmap value (0-256 inclusive)
	 */
	private static function recalculateHeightMapColumn(Chunk $chunk, int $x, int $z, array $directSkyLightBlockers) : int{
		$y = $chunk->getHighestBlockAt($x, $z);
		if($y === null){
			return World::Y_MIN;
		}
		for(; $y >= World::Y_MIN; --$y){
			if(isset($directSkyLightBlockers[$chunk->getBlockStateId($x, $y, $z)])){
				break;
			}
		}

		return $y + 1;
	}
}
