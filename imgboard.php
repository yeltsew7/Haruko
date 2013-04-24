<?php
session_start();
if (!file_exists("./config.php"))
{
header("Location: ./install.php");
}

include("config.php");
include("inc/common.php");
include("inc/common.caching.php");
include("inc/common.posting.php");
include("inc/common.plugins.php");
include("inc/admin.common.php");

if (!empty($_POST['mode']))
{
	$mod = 0;
	$mod_type = 0;
	if ((!empty($_GET['mod'])) && ($_GET['mod']==1))
	{
		if ((!empty($_POST['board'])) || (isBoard($conn, $_POST['board'])))
		{
			canBoard($_POST['board']);
			$mod = 1;
			if (!empty($_SESSION['type'])) { $mod_type = $_SESSION['type']; }
		}
	}
	$conn = new mysqli($db_host, $db_username, $db_password, $db_database);
	loadPlugins($conn);
	$mode = $_POST['mode'];
	switch($mode)
	{
		case "regist":
			$filename = null;
			if (empty($_POST['board']))
			{
			?>
			
<html>
<head>
<title>Error</title>
</head>
<body>
			<?php
				echo "<center><h1>No board selected!</h1></center></body></html>";
				exit;
			}
			$board = $_POST['board'];
			if ($mod == 0)
			{
				banMessage($conn, $board);
			}
			$ignoresizelimit = 0;
			if ($mod == 1)
			{
				if ((!empty($_POST['ignoresizelimit'])) && ($_POST['ignoresizelimit']==1) && ($mod_type >= 1))
				{
					$ignoresizelimit = 1;
				}
			}
			if (!isBoard($conn, $_POST['board']))
			{
				echo "<h1>This board does not exist!</h1></body></html>"; exit;
			}
			?>
<html>
<head>
<title>Updating index</title>
</head>
<body>
<center><h1>Updating Index...</h1></center>
			<?php
			
			$md5 = "";
			$bdata = getBoardData($conn, $_POST['board']);
			if ($bdata['hidden'] == 1)
			{
				echo "<h1>This board does not exist!</h1></body></html>"; exit;
			}
			
			if (strlen($_POST['com']) > $bdata['maxchars'])
			{
				echo "<h1>Comment too long (".strlen($_POST['com'])."/".$bdata['maxchars'].")!</h1></body></html>"; exit;
			}
			
			if ((!empty($_POST['embed'])) && (!empty($_FILES['upfile']['tmp_name'])))
			{
				echo "<center><h1>Choose one: image or embed! ;_;</h1></center></body></html>";
				exit;
			}
			if ((isWhitelisted($conn, $_SERVER['REMOTE_ADDR']) != 2) && (($mod == 0) || ($mod_type==0)))
			{
				$lastdate = $conn->query("SELECT date FROM posts WHERE ip='".$_SERVER['REMOTE_ADDR']."' AND board='".$_POST['board']."' ORDER BY date DESC LIMIT 0, 1");
				if ($lastdate->num_rows == 1)
				{
					$pdate = $lastdate->fetch_assoc();
					$pdate = $pdate['date'];
					
					if (($pdate + $bdata['time_between_posts']) > time())
					{
						echo "<center><h1>You'll have to wait more before posting a new post! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></center></body></html>";
						exit;
					}
				}
				
				$lastdate = $conn->query("SELECT date FROM posts WHERE ip='".$_SERVER['REMOTE_ADDR']."' AND resto=0 AND board='".$_POST['board']."' ORDER BY date DESC LIMIT 0, 1");
				if ($lastdate->num_rows == 1)
				{
					$pdate = $lastdate->fetch_assoc();
					$pdate = $pdate['date'];
					
					if (($pdate + $bdata['time_between_threads']) > time())
					{
						echo "<center><h1>You'll have to wait more before posting a new thread! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></center></body></html>";
						exit;
					}
				}
			}
			if (!empty($_POST['embed']))
			{
				if ($bdata['embeds']==0)
				{
					echo "<center><h1>Embed not supported! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></center></body></html>";
					exit;
				}
				
				$embed_table = array();
				$result = $conn->query("SELECT * FROM embeds;");
				while ($row = $result->fetch_assoc())
				{
					$embed_table[] = $row;
				}
				if (isEmbed($_POST['embed'], $embed_table))
				{
					$filename = "embed:".$_POST['embed'];
				} else {
					echo "<center><h1>Embed not supported! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></center></body></html>";
					exit;
				}
			} else {
				if ((empty($_FILES['upfile']['tmp_name'])) && (!empty($_FILES['upfile']['name'])))
				{
					echo "<h1>File size too big! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></body></html>";
					exit;
				}
				if (!empty($_FILES['upfile']['tmp_name']))
				{
					$target_path = "./".$board."/src/";
					$file_size = $_FILES['upfile']['size'];
					if (($file_size > $bdata['filesize']) && ($ignoresizelimit != 1))
					{
						echo "<h1>File size too big! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></body></html>";
						exit;
					}
					if (!($ext = isImage($_FILES['upfile']['tmp_name'])))
					{
						echo "<h1>File is not an image! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></body></html>";
						exit;
					}
					$fileid = time() . mt_rand(10000000, 999999999);
					$filename = $fileid . $ext; 
					$target_path .= $filename;
					$md5 = md5_file($_FILES['upfile']['tmp_name']);
					if (($bdata['nodup'] == 1) && (($mod == 0) || ($mod_type == 0)))
					{
						$isit = $conn->query("SELECT * FROM posts WHERE filehash='".$md5."' AND board='".$_POST['board']."'");
						if ($isit->num_rows >= 1)
						{
							echo "<h1>Duplicate file detected! [<a href='./".$_POST['board']."/'>RETURN</a>]</h1></body></html>";
							exit;
						}
					}
					if(move_uploaded_file($_FILES['upfile']['tmp_name'], $target_path)) {
						echo "The file ".basename( $_FILES['upfile']['name'])." has been uploaded";
					} else {
						echo "There was an error uploading the file, please try again!";
						$filename = "";
					}
				}
			}

			$name = "Anonymous";
			if ((!empty($_POST['name'])) && (($bdata['noname'] == 0) || (($mod == 1) && ($mod_type >= 1)))) { $name = $_POST['name']; }
			$resto = 0;
			if (isset($_POST['resto'])) { $resto = $_POST['resto']; }
			$password = "";
			if (empty($_POST['pwd']))
			{
				if (isset($_COOKIE['password']))
				{
					$password = $_COOKIE['password'];
				} else {
					$password = randomPassword();
				}
			} else {
				$password = $_POST['pwd'];
			}
			$thumb_w = 0;
			$thumb_h = 0;
			if (substr($filename, 0, 6) != "embed:")
			{
				if (!empty($_FILES['upfile']['tmp_name']))
				{
					if ($resto != 0)
					{
						$returned = thumb($board, $fileid.$ext, 125);
						if ((empty($returned['width'])) || (empty($returned['height'])))
						{
							echo "<h1>Could not create thumbnail!</h1></body></html>"; exit;
						}
						$thumb_w = $returned['width'];
						$thumb_h = $returned['height'];
					} else {
						$returned = thumb($board, $fileid.$ext);
						if ((empty($returned['width'])) || (empty($returned['height'])))
						{
							echo "<h1>Could not create thumbnail!</h1></body></html>"; exit;
						}
						$thumb_w = $returned['width'];
						$thumb_h = $returned['height'];
					}
				}
			}
			$capcode = 0;
			$raw = 0;
			$sticky = 0;
			$lock = 0;
			$nolimit = 0;
			$fake_id = "";
			
			if (!empty($_POST['name'])) { setcookie("mitsuba_name", $_POST['name'], time() + 86400*256); } else { setcookie("mitsuba_name","", time() + 86400*256); }
			if ((!empty($_POST['email'])) && ($_POST['email'] != "sage")) { setcookie("mitsuba_email", $_POST['email'], time() + 86400*256); } else { setcookie("mitsuba_email","", time() + 86400*256); }
			if (!empty($_POST['fake_id'])) { setcookie("mitsuba_fakeid", $_POST['fake_id'], time() + 86400*256); } else { setcookie("mitsuba_fakeid","", time() + 86400*256); }
			
			if (($mod == 1) && ($mod_type>=1))
			{
				if ((!empty($_POST['nolimit'])) && ($_POST['nolimit']==1))
				{
					$nolimit = 1;
				}
				if ((!empty($_POST['capcode'])) && ($_POST['capcode']==1))
				{
					$capcode = $mod_type;
				}
				if ((!empty($_POST['raw'])) && ($_POST['raw']==1))
				{
					$raw = 1;
				}
				if ((!empty($_POST['sticky'])) && ($_POST['sticky']==1))
				{
					$sticky = 1;
				}
				if ((!empty($_POST['lock'])) && ($_POST['lock']==1))
				{
					$lock = 1;
				}
				if (!empty($_POST['fake_id']))
				{
					$fake_id = $_POST['fake_id'];
				}
			}
			$spoiler = 0;
			if ((!empty($_POST['spoiler'])) && ($_POST['spoiler'] == 1) && ($bdata['spoilers'] == 1) && (substr($filename, 0, 6) != "embed:"))
			{
				$spoiler = 1;
			}
			setcookie("password", $password, time() + 86400*256);
			$embed = 0;
			if (substr($filename, 0, 6) != "embed:")
			{
				$fname = $_FILES['upfile']['name'];
				$filename = "";
				if (empty($_FILES['upfile']['tmp_name']))
				{
					$fname = "";
				} else {
					$filename = $fileid.$ext;
				}
			} else {
				$embed = 1;
				$fname = "embed";
			}
			$is = addPost($conn, $_POST['board'], $name, $_POST['email'], $_POST['sub'], $_POST['com'], $password, $filename, $fname, $resto, $md5, $thumb_w, $thumb_h, $spoiler, $embed, $mod_type, $capcode, $raw, $sticky, $lock, $nolimit, $fake_id);
			if ($is == -16)
			{
					echo "<h1>This board does not exist!</h1></body></html>"; exit;
			}
			break;
		case "usrform":
			if (!empty($_POST['delete']))
			{
				$onlyimgdel = 0;
				$password = "";
				if (empty($_POST['board']))
				{
					echo "<h1>No board selected!</h1></body></html>";
					exit;
				}
				$board = $_POST['board'];
				banMessage($conn, $board);
				$password = "";
				if ($mod == 0)
				{
					if (isset($_COOKIE['password'])) { $password = $_COOKIE['password']; }
					if (!empty($_POST['pwd'])) { $password = $_POST['pwd']; }
				}
				if ((isset($_POST['onlyimgdel']) && ($_POST['onlyimgdel'] == "on"))) { $onlyimgdel = 1; }
				foreach ($_POST as $key => $value)
				{
					if ($value == "delete")
					{
						$done = deletePost($conn, $_POST['board'], $key, $password, $onlyimgdel);
						if ($done == -1) {
							echo "Bad password for post ".$key.".<br />";
						} elseif ($done == -2) {
							echo "Post ".$key." not found.<br />";
						} elseif ($done == -3) {
							echo "Post ".$key." has no image.<br />";
						} elseif ($done == -4) {
							echo "You'll have to wait more before deleting post ".$key.".<br />";
						} elseif ($done == 1) {
							echo "Deleted image from post ".$key.".<br />";
						} elseif ($done == 2) {
							echo "Deleted post ".$key.".<br />";
						}
						if ($done == -16)
						{
							echo "<h1>This board does not exist!</h1></body></html>"; exit;
						}
					}
				}
				echo '<meta http-equiv="refresh" content="2;URL='."'./".$_POST['board']."/index.html'".'">';
			} elseif (!empty($_POST['report'])) {
				if (empty($_POST['board']))
				{
					echo "<h1>No board selected!</h1></body></html>";
					exit;
				}
				$board = $_POST['board'];
				banMessage($conn, $board);
				foreach ($_POST as $key => $value)
				{
					if ($value == "delete")
					{
						$done = reportPost($conn, $_POST['board'], $key, $_POST['reason']);
						if ($done == 1)
						{
							echo "Post ".$key." reported.<br />";
						}
					}
				}
				if ($mod == 1)
				{
					echo '<meta http-equiv="refresh" content="2;URL='."'./mod.php?/board&b=".$_POST['board']."'".'">';
				} else {
					echo '<meta http-equiv="refresh" content="1;URL='."'./".$_POST['board']."/index.html'".'">';
				}
			}
			break;
		case "usrapp":
			//$_POST['email']; $_POST['msg'];
			if (!empty($_POST['msg']))
			{
				$msg = $conn->real_escape_string(htmlspecialchars($_POST['msg']));
				$email = $conn->real_escape_string(htmlspecialchars($_POST['email']));
				$ip = $_SERVER['REMOTE_ADDR'];
				$ban = isBanned($conn, $ip, $_POST['board']);
				$ban_id = $ban['id'];
				$range = 0;
				if (!empty($bandata['start_ip'])) { $range = 1; }
				$conn->query("INSERT INTO appeals (created, ban_id, ip, msg, email, rangeban) VALUES (".time().", ".$ban_id.", '".$ip."', '".$msg."', '".$email."', ".$range.")");
				echo "Your appeal has been sent. Keep calm and wait for reply";
			}
			break;
	}
	mysqli_close($conn);
} else {

}
?>
</body>
</html>
