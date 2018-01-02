<?php

namespace BlockInfo;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\math\Vector3;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\entity\Item as ItemEntity;
use pocketmine\item\Item;
use pocketmine\entity\Entity;

use pocketmine\utils\UUID;
use pocketmine\nbt\NBT;
use pocketmine\network\Network;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddPlayerPacket;

use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use pocketmine\scheduler\Task;
class BlockInfo extends PluginBase implements Listener{

          public function onEnable(){
          date_default_timezone_set('Asia/Tokyo');
          	$this->saveResource("setting.yml", false);
			if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder(), 0755, true); 
			}
				
			$this->db = new \SQLite3($this->getDataFolder().'BlockInfo'.'.sqlite3');
			$this->db->exec(
				"CREATE TABLE IF NOT EXISTS PlaceInfo(
					x int,y int,z int,level varchar(255),name varchar(255),datetime datetime,block varchar(255)
				)"
        	);
        	
        		$this->db->exec(
				"CREATE TABLE IF NOT EXISTS BreakInfo(
					x int,y int,z int,level varchar(255),name varchar(255),datetime datetime,block varchar(255)
				)"
        	);
				$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, array(
            "ID" => "339",
            "表示時間(秒)" => 10,
            "ブロック破壊の記録" => false,
            ));
        	$this->id = $this->config->getAll();
            

            $this->pos = [];
            $this->eids = [];
            $this->alr = [];

            $this->getServer()->getPluginManager()->registerEvents($this,$this);

            $this->getLogger()->info("§aBlockInfoをダウンロードしていただきありがとうございます。 §c作者 Reo7s");
			$this->getLogger()->info("§aこのプラグインの二次配布は§c許可§aしますが、JPforum等でコメントしていただければと思います。");
			$this->getLogger()->info("§aそれに加えて作者を偽るのはやめてください。");
			$this->getLogger()->info("§a不具合が発生した場合は、§cLobi[http://j.mp/Lobi_Reo7s]§aまであるいは§cJPforum§aまで");
			$this->getLogger()->info("§aCopyright c 2016 Reo7s All Rights Reserved.");
		}

	public function onBlockPlace(BlockPlaceEvent $event){

		$player = $event->getPlayer();
		$name = strtolower($player->getName());
		$item = $event->getItem()->getName();
		$x = $event->getBlock()->x;
		$y = $event->getBlock()->y;
		$z = $event->getBlock()->z;
		$level = $player->getLevel()->getName();
		$time = date("Y/m/d H : i");
		$data = $this->db->prepare("SELECT * FROM PlaceInfo WHERE x={$x} and y={$y} and z={$z} and level='{$level}'");
		$data = $data->execute();
		$data = $data->fetchArray(SQLITE3_ASSOC);
		if($data==false){
			$this->db->exec("INSERT INTO PlaceInfo (x,y,z,level,name,datetime,block) VALUES ({$x},{$y},{$z},'{$level}','{$name}','{$time}','{$item}')");
		}else{
			$this->db->exec("UPDATE PlaceInfo SET name='{$name}', datetime='{$time}', block='{$item}' WHERE x='{$x}' and y='{$y}' and z='{$z}' and level='{$level}'");
		}

	}
	
	public function onBreakBlock(BlockBreakEvent $event){
		if($this->id["ブロック破壊の記録"]){
			$player = $event->getPlayer();
			$name = strtolower($player->getName());
			$item = $event->getBlock()->getName();
			$x = $event->getBlock()->x;
			$y = $event->getBlock()->y;
			$z = $event->getBlock()->z;
			$level = $player->getLevel()->getName();
			$time = date("Y/m/d H : i");
			$data = $this->db->prepare("SELECT * FROM BreakInfo WHERE x={$x} and y={$y} and z={$z} and level='{$level}'");
			$data = $data->execute();
			$data = $data->fetchArray(SQLITE3_ASSOC);
			if($data==false){
				$this->db->exec("INSERT INTO BreakInfo (x,y,z,level,name,datetime,block) VALUES ({$x},{$y},{$z},'{$level}','{$name}','{$time}','{$item}')");
			}else{
				$this->db->exec("UPDATE BreakInfo SET name='{$name}', datetime='{$time}', block='{$item}' WHERE x='{$x}' and y='{$y}' and z='{$z}' and level='{$level}'");
			}
		}
	
	}
	
	public function Close($players,$x,$y,$z){
		foreach($players as $p){
			$eid = $this->getEid($p,$x,$y,$z);
			$a = new RemoveEntityPacket;
			$a->eid = $eid;
			$p->dataPacket($a);
		}
		unset($this->alr[$x.','.$y.','.$z]);
	}
	public function onTap(PlayerInteractEvent $event){
		$p = $event->getPlayer();
		$id = (int) $this->id["ID"];
		$level = $p->getLevel()->getName();
		$b = $event->getBlock();
		$X = $b->x;
		$Y = $b->y;
		$Z = $b->z;
		if($p->getInventory()->getItemInHand()->getID() === $id){
		$data = $this->db->prepare("SELECT * FROM PlaceInfo WHERE x={$X} and y={$Y} and z={$Z} and level='{$level}'");
		$data = $data->execute();
		$data = $data->fetchArray(SQLITE3_ASSOC);
		}
		if($p->getInventory()->getItemInHand()->getID() === $id and $data==true and !isset($this->alr[$X.','.$Y.','.$Z])){
		$this->alr[$X.','.$Y.','.$Z] = 1;
			$players = $this->getServer()->getOnlinePlayers();
			//$text = "§a========§c".$X.",".$Y.",".$Z."[".$level ."]の情報§a========\n"."§6| §b置いた人 >>" .$data['name']."\n§6| §b置いた時間 >>" .$data['datetime']."\n§6| §b置いたブロック >>" .$data['block'];
			$title = "§a========§c".$X.",".$Y.",".$Z."[".$level ."]の情報§a========";
			$text = "§6| §b置いた人 >>" .$data['name']."\n§6| §b置いた時間 >>" .$data['datetime']."\n§6| §b置いたブロック >>" .$data['block'];
			foreach($players as $player){
				$xyz = $X.$Y.$Z;
				$eid = mt_rand(10000,100000);
				$pk = new AddPlayerPacket();	
				$pk->eid = $eid;
				$this->eids[$p->getName()][$xyz] = $eid;
				$pk->uuid = UUID::fromRandom();
				$pk->x = $X;
				$pk->y = $Y - 1.62;
				$pk->z = $Z;
				$pk->speedX = 0;
				$pk->speedY = 0;
				$pk->speedZ = 0;
				$pk->yaw = 0;
				$pk->pitch = 0;
				$pk->item = Item::get(0);
			$flags = 0;
			$flags |= 1 << Entity::DATA_FLAG_INVISIBLE;
			$flags |= 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
			$flags |= 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
			$flags |= 1 << Entity::DATA_FLAG_IMMOBILE;
			$pk->metadata = [
				Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $title . ($text !== "" ? "\n" . $text : "")],
				Entity::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG, -1],
            ];
				/*$pk->metadata = [
					Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE],
					Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text],
					Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1],
					Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1],
					Entity::DATA_LEAD_HOLDER => [Entity::DATA_TYPE_LONG, -1],
					Entity::DATA_LEAD => [Entity::DATA_TYPE_BYTE, 0]
				                ];*/
				$player->dataPacket($pk);
			}

		$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, 'Close'], [$players,$X,$Y,$Z]), 20 * $this->id["表示時間(秒)"]);


		}elseif($p->getInventory()->getItemInHand()->getID() === $id and !isset($this->alr[$X.','.$Y.','.$Z])){
		$this->alr[$X.','.$Y.','.$Z] = 1;
			$players = $this->getServer()->getOnlinePlayers();
			//$text = "§a========§c".$X.",".$Y.",".$Z."[".$level ."]の情報§a========\n"."§cデータがありません。\nこのプラグインの導入前に置かれたブロックか\nブロック生成プラグインで置かれたブロックの可能性があります。";
			$title = "§a========§c".$X.",".$Y.",".$Z."[".$level ."]の情報§a========";
			$text = "§cデータがありません。\nこのプラグインの導入前に置かれたブロックか\nブロック生成プラグインで置かれたブロックの可能性があります。";
			foreach($players as $player){
				$xyz = $X.$Y.$Z;
				$eid = mt_rand(10000,100000);
				$pk = new AddPlayerPacket();
				$pk->eid = $eid;
				$this->eids[$p->getName()][$xyz] = $eid;
				$pk->uuid = UUID::fromRandom();
				$pk->x = $X;
				$pk->y = $Y - 1.62;
				$pk->z = $Z;
				$pk->speedX = 0;
				$pk->speedY = 0;
				$pk->speedZ = 0;
				$pk->yaw = 0;
				$pk->pitch = 0;
				$pk->item = Item::get(0);
			$flags = 0;
			$flags |= 1 << Entity::DATA_FLAG_INVISIBLE;
			$flags |= 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG;
			$flags |= 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG;
			$flags |= 1 << Entity::DATA_FLAG_IMMOBILE;
			$pk->metadata = [
				Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $title . ($text !== "" ? "\n" . $text : "")],
				Entity::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG, -1],
            ];
				$player->dataPacket($pk);
			}

		$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, 'Close'], [$players,$X,$Y,$Z]), 20 * $this->id["表示時間(秒)"]);

		}elseif($p->getInventory()->getItemInHand()->getID() === 325){
		$dt = $event->getFace();
		$b=$event->getBlock();
		$x=$b->x;
		$y=$b->y;
		$z=$b->z;
		switch($dt){
		case "0":
		$y--;
		break;
		case "1":
		$y++;
		break;
		case "2":
		$z--;
		break;
		case "3":
		$z++;
		break;
		case "4":
		$x--;
		break;
		case "5":
		$x++;
		break;
		}
		$name = strtolower($p->getName());
		$damage = $event->getItem()->getDamage();
		if($damage===8){
		$item="Water Bucket";
		}elseif($damage===10){
		$item="Lava Bucket";
		}else{
		return;
		}
		
		$level = $p->getLevel()->getName();
		$time = date("Y/m/d H : i");
		$data = $this->db->prepare("SELECT * FROM PlaceInfo WHERE x={$x} and y={$y} and z={$z} and level='{$level}'");
		$data = $data->execute();
		$data = $data->fetchArray(SQLITE3_ASSOC);
		if($data==false){
			$this->db->exec("INSERT INTO PlaceInfo (x,y,z,level,name,datetime,block) VALUES ({$x},{$y},{$z},'{$level}','{$name}','{$time}','{$item}')");
		}else{
			$this->db->exec("UPDATE PlaceInfo SET name='{$name}', datetime='{$time}', block='{$item}' WHERE x='{$x}' and y='{$y}' and z='{$z}' and level='{$level}'");
		}
		}
	}
	
	public function getEid(Player $p, $x, $y, $z){
	$xyz = $x.$y.$z;

		if($this->eids[$p->getName()][$xyz] === null){
			$a = false;
		}else{
			$a = $this->eids[$p->getName()][$xyz];
		}
	return $a;
	}

	public function onCommand(CommandSender $sender,Command $command,$label,array $args){
		//if(!$sender instanceof Player) return $sender->sendMessage("§c[エラー]ゲーム内でご確認ください。");
		if($sender instanceof Player){
		$level = $sender->getLevel()->getName();
		}
	if(isset($args[0])){
		$args[0] = strtolower($args[0]);
	}
	if(isset($args[2])){
		$args[2] = strtolower($args[2]);
	}
		switch(strtolower($command->getName())){
		case "blockinfo":
		case "bi":
			if(empty($args[1])){
				$id = (int) $this->id["ID"];
				$sender->sendMessage("§a========[BlockInfo]========");
				$sender->sendMessage("§bBlockInfoではブロック設置・破壊時の情報を保存します。");
				if($sender instanceof Player){
				$sender->sendMessage("§6| §c/bi <place/break> <座標>§a -- §bワールド名がわからず自分の今いるワールドを調べるとき");
				$sender->sendMessage("§6| §b例:/bi place 0,4,0");
				}
				$sender->sendMessage("§6| §c/bi <place/break> <座標> <ワールド名>§a -- §bワールド名がわかる場合§c※フォルダの名前とは違うので注意");
				$sender->sendMessage("§6| §b例:/bi break 0,4,0 world");
				$sender->sendMessage("§6| §e(ItemID)".$id."でブロックタップで確認も可能です。");
				return false;
			}elseif(empty($args[2]) and $sender instanceof Player){
			if($args[0]!='place' and $args[0]!='break'){
			$sender->sendMessage("§e>>入力に誤りがあります。");
			return;
			}elseif($args[0]=='place'){
			$table='Place';
			$pb='置いた';
			}else{
			$table='Break';
			$pb='壊した';
			if(!$this->id["ブロック破壊の記録"]){
			$sender->sendMessage("§e>>現在ブロック破壊の情報は保存されないようになっています。");
			}
			}
				$pos = explode(',', $args[1]);
				if(!isset($pos[2]) or !$this->is_numeric_array($pos)){
					$sender->sendMessage("§e>>入力が正しくありません。§b【x,y,z】§eの形で入力して下さい。");
				}else{
					$data = $this->db->prepare("SELECT * FROM {$table}Info WHERE x={$pos[0]} and y={$pos[1]} and z={$pos[2]} and level='{$level}'");
					$data = $data->execute();
					$data = $data->fetchArray(SQLITE3_ASSOC);
						if($data){
								$sender->sendMessage("§a========§c" .$args[1]."[".$level ."]の情報§a========");
								$sender->sendMessage("§6| §b{$pb}人 >>" .$data['name']);
								$sender->sendMessage("§6| §b{$pb}時間 >>" .$data['datetime']);
								$sender->sendMessage("§6| §b{$pb}ブロック >>" .$data['block']);
						}else{
							$sender->sendMessage("§e>>" .$args[1]."[".$level ."]のデータが見つかりませんでした。");
						}
				}

			}else{
			if($args[0]!='place' and $args[0]!='break'){
			$sender->sendMessage("§e>>入力に誤りがあります。");
			return;
			}elseif($args[0]=='place'){
			$table='Place';
			$pb='置いた';
			}else{
			$table='Break';
			$pb='壊した';
			if(!$this->id["ブロック破壊の記録"]){
			$sender->sendMessage("§e>>現在ブロック破壊の情報は保存されないようになっています。");
			}
			}
			if(empty($args[2])) return $sender->sendMessage("§e>>ワールド名が入力されていません。");
				$pos = explode(',', $args[1]);
				if(!isset($pos[2]) or !$this->is_numeric_array($pos)){
					$sender->sendMessage("§e>>入力が正しくありません。§b【x,y,z WorldName】§eの形で入力して下さい。");
				}else{
					$data = $this->db->prepare("SELECT * FROM {$table}Info WHERE x={$pos[0]} and y={$pos[1]} and z={$pos[2]} and level='{$args[2]}'");
					$data = $data->execute();
					$data = $data->fetchArray(SQLITE3_ASSOC);
						if($data){
								$sender->sendMessage("§a========§c" .$args[1]."[".$args[2] ."]の情報§a========");
								$sender->sendMessage("§6| §b{$pb}人 >>" .$data['name']);
								$sender->sendMessage("§6| §b{$pb}時間 >>" .$data['datetime']);
								$sender->sendMessage("§6| §b{$pb}ブロック >>" .$data['block']);
						}else{
							$sender->sendMessage("§e>>" .$args[1]."[".$args[2] ."]のデータが見つかりませんでした。");
						}
				}
			}
		default:
		return true;
		break;
			}
		}


public function is_numeric_array($array) {
    foreach ($array as $value) {
        if (!is_numeric($value)) {
            return false;
        }
    }
    return true;
}


}