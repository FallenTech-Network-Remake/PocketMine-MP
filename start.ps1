[CmdletBinding(PositionalBinding=$false)]
param (
	[string]$php = "",
	[switch]$Loop = $false,
	[string]$file = "",
	[string][Parameter(ValueFromRemainingArguments)]$extraPocketMineArgs
)

if($php -ne ""){
	$binary = $php
}elseif(Test-Path "bin\php\php.exe"){
	$env:PHPRC = ""
	$binary = "bin\php\php.exe"
}elseif((Get-Command php -ErrorAction SilentlyContinue)){
	$binary = "php"
}else{
	echo "Couldn't find a PHP binary in system PATH or $pwd\bin\php"
	exit 1
}

# Ensure background DB services (MariaDB / MySQL & Redis) are active
if (Get-Command wsl -ErrorAction SilentlyContinue) {
	try {
		wsl -u root -d Ubuntu -e bash -c "service mariadb start; service redis-server start" *>$null
	} catch {}
}

if($file -eq ""){
	if(Test-Path "PocketMine-MP.phar"){
	    $file = "PocketMine-MP.phar"
	}elseif(Test-Path "src\PocketMine.php"){
	    $file = "src\PocketMine.php"
	}else{
	    echo "PocketMine-MP.phar or src\PocketMine.php not found"
	    echo "Downloads can be found at https://github.com/pmmp/PocketMine-MP/releases"
	    pause
	    exit 1
	}
}

function StartServer{
	$command = "powershell -NoProfile " + $binary + " " + $file + " " + $extraPocketMineArgs
	iex $command
}

$loops = 0

StartServer

while($Loop){
	if($loops -ne 0){
		echo ("Restarted " + $loops + " times")
	}
	$loops++
	echo "To escape the loop, press CTRL+C now. Otherwise, wait 5 seconds for the server to restart."
	echo ""
	Start-Sleep 5
	StartServer
}