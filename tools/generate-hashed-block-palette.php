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

/*
 * FALLENTECH (kqg, 2026-09-16): this is where resources/block_palette.nbt comes from.
 *
 * Nobody could answer that question during the 1.26.50 breakage, so the stale 1.26.40
 * palette survived a protocol bump and the serializer emitted states the lookup table had
 * never seen. Run this on every protocol bump, BEFORE trusting the new vendor tree:
 *
 *   php tools/generate-hashed-block-palette.php vendor/axolotl-pm/bedrock-data resources
 *
 * WHAT IT WRITES
 *   resources/block_palette.nbt               gzip stream of a BigEndian NBT root compound
 *                                             holding one "blocks" TAG_List of TAG_Compound
 *   resources/block_state_meta_map_hashed.json compact json_encode() of the POSITIONAL
 *                                             legacy-meta list, same order as the palette
 *
 * Both come from the same source file in the same pass, so the positional pairing the
 * loader relies on (BlockStateDictionary::loadFromString, $metaMap[$i]) is correct by
 * construction. Generating them separately is how 402 states ended up carrying the wrong
 * legacy meta before this script existed.
 *
 * PALETTE ENTRY FORMAT (field order matters for byte-identical output)
 *   network_id  TAG_Int   FNV-1a 32-bit, read back SIGNED, over the LittleEndianNbtSerializer
 *                         (fixed-width, NOT varint/network NBT) encoding of
 *                         TAG_Compound{name: String, states: Compound} inside a TreeRoot
 *                         whose root tag name is "". init 0x811c9dc5, prime 0x01000193.
 *                         "version" is in the entry but NOT in the hashed payload, and the
 *                         states compound is hashed exactly AS STORED - no sorting.
 *                         Carve-out: minecraft:unknown is the hardcoded sentinel -2, the
 *                         same one CloudburstMC's BlockRegistry::createHash uses.
 *   name_hash   TAG_Long  FNV-1 64-bit (init 14695981039346656037, prime 1099511628211)
 *                         over the raw name string. The loader never reads this, but the
 *                         client-side palettes carry it and dropping it changes the bytes.
 *   name        TAG_String
 *   version     TAG_Int   copied through from the source entry
 *   states      TAG_Compound
 *
 * WHY HASHED IDS AT ALL: keying the dictionary by a hash of (name, states) instead of by
 * palette index makes a block's runtime ID identical on every backend no matter what custom
 * blocks each one registers, which is what keeps proxy transfers from scrambling the world.
 * That guarantee only holds if this hash never changes - do not "improve" it.
 *
 * PROOF THE HASH IS RIGHT (re-run it if you ever touch this file): regenerating the 1.26.40
 * palette from bedrock-data ref e2d2332 reproduces the palette kqg committed in dcce603
 * byte-for-byte, 3247894 bytes of payload:
 *
 *   php tools/generate-hashed-block-palette.php <bedrock-data@e2d2332> <scratch> --palette-only \
 *       --expect-payload-sha256=55cbb7dc3dbfec3b71d33e7ea37d195b83a625f406a89ebf9a6bdae4d8c39d39
 *
 * e2d2332, NOT the 7db37fe that composer.lock pinned at the time - and that is the whole
 * point. The palette was cut from one bedrock-data revision and the meta map from another:
 * the committed meta map is byte-identical to 7db37fe's and the committed palette is in
 * e2d2332's state order, and those two orders disagree at 402 positions (first at index 303,
 * the *_shelf families and straw_bed). Those 402 states have carried the WRONG legacy meta
 * on prod ever since. Taking both files from one directory in one pass is what stops that
 * happening again - do not hand-edit either output.
 *
 * e2d2332 IS AN ORACLE FOR THE PALETTE BYTES ONLY. NEVER REGENERATE resources/ FROM IT
 * (kqg 2026-09-16). "One ref in one pass" gives you whatever that ref ships, correct or not,
 * and this one ships a meta map that does not line up with its OWN canonical_block_states.nbt:
 * building (name,states) -> meta from each revision's own pair, e2d2332 disagrees with 7db37fe
 * on 10979 of 17499 states, while 7db37fe and the pinned 7e6aee3 (1.26.50) disagree on 0 of
 * their 16906 shared states. So the 402-position bug above is only the half of the provenance
 * failure that was visible in the palette. That is why the command above passes --palette-only,
 * and why --expect-payload-sha256 refuses to write into a directory that already holds a
 * block_palette.nbt: a run whose job is to check the hash must never be able to emit a meta map
 * over a good one and still print "MATCHES expected sha256".
 *
 * Only the NBT PAYLOAD is reproducible. The gzip CONTAINER is not: the committed file was
 * deflated by Go (XFL=0/OS=255 in its header) and zlib will not emit those bytes. That is
 * cosmetic - zlib_decode accepts any gzip stream and the runtime only ever sees the payload.
 */

namespace pocketmine\tools\generate_hashed_block_palette;

use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use function array_slice;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function hash;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function json_decode;
use function json_encode;
use function mkdir;
use function ord;
use function preg_match;
use function rename;
use function sprintf;
use function strlen;
use function strtolower;
use function str_starts_with;
use function substr;
use function unlink;
use function zlib_encode;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STDERR;
use const ZLIB_ENCODING_GZIP;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/** minecraft:unknown is not hashed - the client expects this exact sentinel */
const UNKNOWN_BLOCK_NAME = "minecraft:unknown";
const UNKNOWN_BLOCK_NETWORK_ID = -2;

function fnv1a32(string $data) : int{
	$hash = 0x811c9dc5;
	for($i = 0, $len = strlen($data); $i < $len; $i++){
		$hash ^= ord($data[$i]);
		$hash = ($hash * 0x01000193) & 0xFFFFFFFF;
	}
	//the palette stores this as TAG_Int, which is signed - reinterpret the top bit
	return $hash >= 0x80000000 ? $hash - 0x100000000 : $hash;
}

function fnv164(string $data) : int{
	//a native 64x64 multiply overflows PHP's int and silently degrades to float, so the
	//state is carried as two 32-bit halves and multiplied schoolbook. The prime is
	//0x100000001B3, i.e. phi=0x100 / plo=0x1B3, so every partial product stays under 2^42
	//and the hi*phi partial falls entirely off the top of the 64-bit result.
	$hi = 0xcbf29ce4;
	$lo = 0x84222325;
	$phi = 0x00000100;
	$plo = 0x000001b3;
	for($i = 0, $len = strlen($data); $i < $len; $i++){
		$t = $lo * $plo;
		$newLo = $t & 0xFFFFFFFF;
		$hi = ($hi * $plo + $lo * $phi + ($t >> 32)) & 0xFFFFFFFF;
		$lo = $newLo;
		//FNV-1 (not 1a): multiply first, then xor the byte in
		$lo ^= ord($data[$i]);
	}
	//PHP ints are 64-bit two's complement and << discards what overflows, so this is
	//already the signed value TAG_Long wants
	return ($hi << 32) | $lo;
}

function fail(string $message) : never{
	fwrite(STDERR, "ERROR: " . $message . PHP_EOL);
	exit(1);
}

/** @param list<string> $argv */
function main(array $argv) : void{
	$positional = [];
	$expectSha = null;
	$gzipLevel = 9;
	$paletteOnly = false;
	foreach($argv as $arg){
		if(str_starts_with($arg, "--expect-payload-sha256=")){
			$expectSha = strtolower(substr($arg, 24));
		}elseif(str_starts_with($arg, "--gzip-level=")){
			$raw = substr($arg, 13);
			//validated here rather than at zlib_encode(), which throws a ValueError only AFTER the
			//output directory has been created
			if(preg_match('/^-?[0-9]+$/', $raw) !== 1){
				fail("--gzip-level must be an integer, got '$raw'");
			}
			$gzipLevel = (int) $raw;
			if($gzipLevel < -1 || $gzipLevel > 9){
				fail("--gzip-level must be between -1 and 9, got $gzipLevel");
			}
		}elseif($arg === "--palette-only"){
			$paletteOnly = true;
		}elseif(str_starts_with($arg, "--")){
			fail("unknown option $arg");
		}else{
			$positional[] = $arg;
		}
	}
	if(count($positional) !== 2){
		fail("usage: php " . __FILE__ . " <bedrock-data-dir> <output-dir> [--palette-only] [--expect-payload-sha256=HEX] [--gzip-level=N]");
	}
	[$dataDir, $outDir] = $positional;

	//a verification run must not be able to overwrite a good pair - see the e2d2332 note at the top
	if($expectSha !== null && is_file($outDir . "/block_palette.nbt")){
		fail("--expect-payload-sha256 is a verification run and $outDir already holds a block_palette.nbt - point it at an empty scratch dir instead");
	}

	$canonicalPath = $dataDir . "/canonical_block_states.nbt";
	$metaMapPath = $dataDir . "/block_state_meta_map.json";
	foreach([$canonicalPath, $metaMapPath] as $path){
		if(!is_file($path)){
			fail("missing input $path");
		}
	}

	//bedrock-data ships canonical_block_states.nbt as concatenated NETWORK (varint) NBT roots
	$roots = (new NetworkNbtSerializer())->readMultiple(file_get_contents($canonicalPath));
	$metaMap = json_decode(file_get_contents($metaMapPath), true, flags: JSON_THROW_ON_ERROR);
	if(!is_array($metaMap)){
		fail("block_state_meta_map.json did not decode to a list");
	}

	//the loader pairs these two by INDEX, so a length mismatch is silent corruption, not an error
	if(count($roots) !== count($metaMap)){
		fail(sprintf("length mismatch: %d canonical states vs %d meta map entries - the two outputs would not line up", count($roots), count($metaMap)));
	}

	$le = new LittleEndianNbtSerializer();
	$entries = [];
	$seenNetworkIds = [];
	$collisions = 0;
	foreach($roots as $i => $root){
		$tag = $root->mustGetCompoundTag();
		$name = $tag->getString("name");
		$states = $tag->getCompoundTag("states") ?? new CompoundTag();
		$version = $tag->getInt("version");

		if(!is_int($metaMap[$i] ?? null)){
			fail("meta map entry $i is not an int");
		}

		if($name === UNKNOWN_BLOCK_NAME){
			$networkId = UNKNOWN_BLOCK_NETWORK_ID;
		}else{
			$hashPayload = CompoundTag::create()
				->setString("name", $name)
				->setTag("states", $states);
			$networkId = fnv1a32($le->write(new TreeRoot($hashPayload, "")));
		}

		//a collision means two states would share one runtime ID - one of them becomes
		//unreachable, so this is a hard stop, never a warning
		if(isset($seenNetworkIds[$networkId])){
			$collisions++;
			fwrite(STDERR, sprintf("COLLISION: network_id %d shared by '%s' and '%s' (index %d)" . PHP_EOL, $networkId, $seenNetworkIds[$networkId], $name, $i));
		}
		$seenNetworkIds[$networkId] = $name;

		$entries[] = CompoundTag::create()
			->setInt("network_id", $networkId)
			->setLong("name_hash", fnv164($name))
			->setString("name", $name)
			->setInt("version", $version)
			->setTag("states", $states);
	}

	if($collisions !== 0){
		fail("$collisions hash collision(s) - refusing to write a palette with unreachable states");
	}
	if(count($entries) !== count($metaMap)){
		fail(sprintf("built %d palette entries but have %d meta values", count($entries), count($metaMap)));
	}

	$rootTag = CompoundTag::create()->setTag("blocks", new ListTag($entries, NBT::TAG_Compound));
	$payload = (new BigEndianNbtSerializer())->write(new TreeRoot($rootTag, ""));
	$payloadSha = hash("sha256", $payload);

	if($expectSha !== null && $payloadSha !== $expectSha){
		fail("payload sha256 $payloadSha does not match expected $expectSha - the hash scheme is wrong, do NOT ship this");
	}

	if(!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)){
		fail("could not create output dir $outDir");
	}
	$palettePath = $outDir . "/block_palette.nbt";
	$metaMapOutPath = $outDir . "/block_state_meta_map_hashed.json";

	//both outputs are staged and only then renamed into place. A half-written pair is exactly the
	//misalignment this script exists to prevent, and a disk-full between two plain writes would
	//otherwise leave a new palette next to the old meta map and still exit 0.
	$staged = [
		$palettePath => zlib_encode($payload, ZLIB_ENCODING_GZIP, $gzipLevel)
	];
	if(!$paletteOnly){
		$staged[$metaMapOutPath] = json_encode($metaMap, JSON_THROW_ON_ERROR);
	}
	$tmpPaths = [];
	foreach($staged as $path => $contents){
		$tmp = $path . ".tmp";
		$written = file_put_contents($tmp, $contents);
		if($written !== strlen($contents)){
			foreach($tmpPaths as $cleanup){
				unlink($cleanup);
			}
			fail("short or failed write to $tmp - nothing was moved into place");
		}
		$tmpPaths[$path] = $tmp;
	}
	foreach($tmpPaths as $path => $tmp){
		if(!rename($tmp, $path)){
			fail("could not move $tmp into place as $path");
		}
	}

	echo sprintf("states:      %d (0 hash collisions)" . PHP_EOL, count($entries));
	echo sprintf("payload:     %d bytes sha256=%s" . PHP_EOL, strlen($payload), $payloadSha);
	if($expectSha !== null){
		echo "payload:     MATCHES expected sha256" . PHP_EOL;
	}
	echo sprintf("wrote:       %s" . PHP_EOL, $palettePath);
	if($paletteOnly){
		echo sprintf("skipped:     %s (--palette-only)" . PHP_EOL, $metaMapOutPath);
	}else{
		echo sprintf("wrote:       %s (%d entries)" . PHP_EOL, $metaMapOutPath, count($metaMap));
	}
}

main(array_slice($argv, 1));
