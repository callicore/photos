<?php

/*//. protolol - the functions for interfacing with the database.

	db iterface hammered by bob majdak jr

int	put_image($path);                             // (re)create a image in the db with full file path <$path>.
		returns the image id of inserted image.
		
bool    put_thumbnail($iid,$gdi);                     // store thumbnail for image id <$iid> from gd image reference <$gdi>.
bool    put_image_tags($iid,$tagarr,$catarr,$imgarr); // store keywords for image id <$iid> from array <$tagarr>
		tagarr[0] = first tag name
		catarr[0] = first tag category id (null if none)
		imgarr[0] = first tag gd_image resource (null if none)
		
bool    put_tag_category($cat);                       // insert tag category
		returns true on add, false if the tag already existed.

GPixBf  get_thumbnail($iid);                          // get thumbail for image.
objarr  get_image_tags($iid);                         // get image tag words.
objarr  get_tag_categories(void);                     // pull all categories.
obj     get_image_info($iid);                         // get image information
obj     get_image_info_by_path($path);                // get image information looked up by the file path.
		returns false if image with <$path> does not exist.

void    get_image_all($arr,bool build_pixbuf);        // get all images from database.
		void return, all data is built in the reference array <$arr>, arg bool
		true build_pixbuf will load thumbnail pixbuf as well.		
		
void    drop_image($iid);                             // drop an image (and related data) from the db with image id <$iid>.
void    drop_tag_category($cid);                      // remove category of id <$cid>
void    drop_thumbnail($iid);                         // remove thumbnail for image <$iid>.
void    drop_image_tag($iid,$tid);                    // remove tag id <$tid> from image <$iid>
bool    update_image_info($iid,$infoarr);             // update image id <$iid> with data from array <$infoarr>.
bool    update_checksum($iid);                        // update checksum from file with image id <$iid>
int     image_exists($iid);                           // check if image id exists.

int     image_path_exists($iid);                      // check if image with path exists.
		returns false on failure. returns image id if true.

.//*/

class CC_Db {

	var $db;
	var $dbfile;
	var $tagcache;

	const INFO_PATH = 1;
	const INFO_SUM = 2;
	const INFO_NAME = 3;
	const INFO_DATE = 4;
	const INFO_DESC = 5;
	var $ikey;

	function __construct($file) {
	
		//. make dir or open will always fail.
		if(!file_exists(CC::$dir . 'db' . DS)) {
			mkdir(CC::$dir . 'db' . DS);
		}
	
		if(!file_exists($file)) { $new = true; }
		else { $new = false; }

		try {
			
			$this->db = new PDO(sprintf("sqlite:%s",$file));
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING); 
			
		} catch(PDOException $e) {
		
			// on fail, try backup.			
			if(file_exists($file.".backup")) {		
				rename($file,$file.".dead-".time());
				rename($file.".backup",$file);
			}		
		
			try {
				
				$this->db = new PDO(sprintf("sqlite:%s",$file));
				$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);								
				
			} catch(PDOException $e) {
				printf("%s\r\n",$e->getMessage()); die();			
			}

			trigger_error("Unable to load database, previous backup restored.");			
				
		}
		
		if($new) {
			$this->make_database();
		}
		
		$this->dbfile = $file;
		
		$this->ikey = array(
			self::INFO_PATH => "img_path",
			self::INFO_SUM => "img_sum",
			self::INFO_NAME => "i_name",
			self::INFO_DATE => "i_date",
			self::INFO_DESC => "i_desc"					
		);
		
		$this->tagcache = array();
		
	}
	
	/*//. database maintainance
	../ -------------------------- .//*/	
	
	//. this should not be called unless you wish to drop the database and create fresh.
	function make_database() {
		$this->db->query("DROP TABLE IF EXISTS images");
		$this->db->query("DROP TABLE IF EXISTS info");
		$this->db->query("DROP TABLE IF EXISTS thumbnails");
		$this->db->query("DROP TABLE IF EXISTS tag");
		$this->db->query("DROP TABLE IF EXISTS tag_link");
		
		$this->db->query("CREATE TABLE images (img_id INTEGER PRIMARY KEY AUTOINCREMENT, img_path TEXT, img_sum TEXT)");
		$this->db->query("CREATE TABLE info (img_id INTEGER PRIMARY KEY, i_date INTEGER, i_name TEXT, i_desc TEXT)");
		$this->db->query("CREATE TABLE thumbnails (img_id INTEGER PRIMARY KEY, thm_data BLOB)");
		$this->db->query("CREATE TABLE tag (tag_id INTEGER PRIMARY KEY AUTOINCREMENT, tag_word TEXT, tag_thm_data BLOB, cat_id INTEGER)");
		$this->db->query("CREATE TABLE tag_category (cat_id INTEGER PRIMARY KEY AUTOINCREMENT, cat_name TEXT, cat_stock TEXT)");
		$this->db->query("CREATE TABLE tag_link (tag_id INTEGER, img_id INTEGER)");
		$this->db->query("INSERT INTO tag_category (cat_name, cat_stock) VALUES ('People', 'cc-photo')");
		$this->db->query("INSERT INTO tag_category (cat_name, cat_stock) VALUES ('Places', 'cc-home')");
		$this->db->query("INSERT INTO tag_category (cat_name, cat_stock) VALUES ('Events',  'cc-date')");
		$this->db->query("INSERT INTO tag_category (cat_name, cat_stock) VALUES ('Other', 'cc-attach')");
		
	}
	
	function shutdown() {
		$this->db = null;
		
		//. backup.
		copy($this->dbfile,$this->dbfile.".backup");
	}
	
	/*//. misc functions
	../ -------------------------- .//*/
	
	function capture_thumbnail_gd($gdi,&$output) {
		ob_start();
		imagepng($gdi);
		$output = ob_get_clean();
		imagedestroy($gdi);
	}
	
	function build_pixbuf_from_data(&$data,&$output) {
		$tmp = tempnam("",".imagethumb-");
		file_put_contents($tmp,$data);
		if(filesize($tmp)) {
			$output = GdkPixbuf::new_from_file($tmp);
		} unlink($tmp);
	}
	

	/*//. insert functions
	../ -------------------------- .//*/
	
	//. put an image in the database.
	//. creates the basic database row set for an image.
	//. this function replaces any old occurences of the same path.
	function put_image($path) {
		$sum = md5_file($path);
		
		$r = $this->db->prepare("SELECT img_id FROM images WHERE img_path=:path");
		$r->bindValue(":path",$path); $r->execute();
		$ret = $r->fetchObject(); $r = null;
		
		if(is_object($ret) && $ret->img_id) {
			$this->db->query(sprintf("DELETE FROM images WHERE img_id='%d'",$ret->img_id));
			$this->db->query(sprintf("DELETE FROM thumbnails WHERE img_id='%d'",$ret->img_id));
			$this->db->query(sprintf("DELETE FROM info WHERE img_id='%d'",$ret->img_id));
			$this->db->query(sprintf("DELETE FROM tag_link WHERE img_id='%d'",$ret->img_id));
		}
		
		$r = $this->db->prepare("INSERT INTO images (img_path, img_sum) VALUES (:path, :sum)");
		$r->bindValue(':path',$path); $r->bindValue(':sum',$sum);
		$r->execute(); $iid = $this->db->lastInsertId(); $r = null;
		
		$this->db->query(sprintf(
			"INSERT INTO info (img_id,i_name,i_date) VALUES ('%d','%s','%d');",
			$iid,
			preg_replace("/\.(.+)$/","",basename($path)),
			time()
		));
		
		return $iid;
	}
	
	//. store a thumbnail.
	//. turns a gd_image resource into a stored png.
	function put_thumbnail($iid,$gdi) {
		
		$r = $this->db->query(sprintf("SELECT * FROM images WHERE img_id='%s'",$iid));
		$ret = $r->fetchObject(); $r = null;
		
		if(!is_object($ret)) {
			return false;
		} $ret = false;

		$r = $this->db->query(sprintf("SELECT img_id FROM thumbnails WHERE img_id='%s'",$iid));
		$ret = $r->fetchObject(); $r = null;

		self::capture_thumbnail_gd($gdi,$png);
		
		if(!is_object($ret)) {
			$r = $this->db->prepare(sprintf("INSERT INTO thumbnails (img_id, thm_data) VALUES ('%d', :data)",$iid));
		} else {
			$r = $this->db->prepare(sprintf("UPDATE thumbnails SET thm_data=:data WHERE img_id='%d'",$iid));
		}
		
		if(is_object($r)) {
			$r->bindValue(":data",$png);
			$r->execute();
		}

		return true;		
	}
	
	//. store image tags.
	//. takes tags and stores them in relation to an image with category and thumbnails.
	function add_tag($iid,$tid) {

		$this->tagcache[$iid] = array();
	
		$r = $this->db->query(sprintf("SELECT * FROM images WHERE img_id='%s'",$iid));
		$ret = $r->fetchObject(); $r = null;
		
		if(!is_object($ret)) { return false; }
		
			$r = $this->db->query(sprintf("SELECT tag_id FROM tag WHERE tag_id='%s'",$tid));
			$ret = $r->fetchObject(); $r = null;

			$r = $this->db->query(sprintf("SELECT tag_id FROM tag_link WHERE tag_id='%s' AND img_id='%d'",$tid,$iid));
			$ret = $r->fetchObject(); $r = null;
			
			if(!is_object($ret)) {
				$r = $this->db->prepare("INSERT INTO tag_link (tag_id, img_id) VALUES (:tid, :iid)");
				$r->bindValue(":tid",$tid); $r->bindValue(":iid",$iid);$r->execute(); $r = null;
				return true;
			} else {
				return false;
			}

		return true;
	}
	
	//. insert new category for tags.
	function put_tag($name, $cname, $img = null) {
			$r = $this->db->prepare("SELECT cat_id FROM tag_category WHERE cat_name=:cat");
			$r->bindValue(":cat",$cname); $r->execute();
			$ret = $r->fetchColumn(); $r = null;
			
				if(!is_null($img))
				self::capture_thumbnail_gd($img,$png);
				else
				$png = null;
				$r = $this->db->prepare("INSERT INTO tag (tag_word, tag_thm_data, cat_id) VALUES (:word, :img, :cat)");
				$r->bindValue(":cat",$ret); $r->bindValue(":word",$name); $r->bindValue(":img",$png);$r->execute(); $r = null;
				return true;
	}

	/*//. delete functions
	../ -------------------------- .//*/

	//. delete an image with id from the database.
	function drop_image($img_id) {
		$this->db->query(sprintf("DELETE FROM images WHERE img_id='%d'",$img_id));
		$this->db->query(sprintf("DELETE FROM thumbnails WHERE img_id='%d'",$img_id));
		$this->db->query(sprintf("DELETE FROM info WHERE img_id='%d'",$img_id));
		$this->db->query(sprintf("DELETE FROM tag_link WHERE img_id='%d'",$img_id));
	}
	
	//. delete a category and take any tags using it out of it.
	function drop_tag_category($cat_id) {
		$this->db->query(sprintf("DELETE FROM tag_category WHERE cat_id='%d'",$cat_id));
		$this->db->query(sprintf("UPDATE tag_link SET cat_id='0' WHERE cat_id='%d'",$cat_id));
	}
	
	//. delete a thumbnail.
	function drop_thumbnail($img_id) {
		$this->db->query(sprintf("UPDATE thumbnails SET thm_data='' WHERE img_id='%d'",$img_id));
	}
	
	//. delete a tag from an image.
	function drop_img_tag($img_id,$tag_id) {
		$this->db->query(sprintf("DELETE FROM tag_link WHERE img_id='%d' AND tag_id='%d'",$img_id,$tag_id));
	}
	//. delete a tag from an image.
	function drop_tag($img_id) {
		$this->db->query(sprintf("DELETE FROM tag_link WHERE tag_id='%d'",$tag_id));
		$this->db->query(sprintf("DELETE FROM tag WHERE tag_id='%d'",$tag_id));
		$this->db->query(sprintf("DELETE FROM tag_cat WHERE tag_id='%d'",$tag_id));
	}

	/*//. update functions
	../ -------------------------- .//*/

	//. update image information with specified data in a variable length array.
	//. use the db keys to mark data fields.
	function update_image_info($iid,$infoarr) {

		$r = $this->db->query(sprintf("SELECT * FROM images WHERE img_id='%s'",$iid));
		$ret = $r->fetchObject(); $r = null;
		
		if(!is_object($ret)) {
			return false;
		}

		$this->db->beginTransaction();

		foreach($infoarr as $type => $data) {
			switch($type) {
				case(self::INFO_PATH): { }
				case(self::INFO_SUM): {
					$r = $this->db->prepare(sprintf(
						"UPDATE images SET %s=:data WHERE img_id='%d'",
						$this->ikey[$type],
						$iid
					)); $r->bindValue(":data", $data); $r->execute(); $r = null;
					break;					
				}
				case(self::INFO_NAME): { }
				case(self::INFO_DATE): { }
				case(self::INFO_DESC): {
					$r = $this->db->prepare(sprintf(
						"UPDATE info SET %s=:data WHERE img_id='%d'",
						$this->ikey[$type],
						$iid
					));
					$r->bindValue(":data", $data); $r->execute(); $r = null;
					break;
				}
			}
		}
		
		$this->db->commit();
		
		return true;
	}
	
	
	//. update an image checksum.
	//. this should be called every time an image is resaved to disk.
	function update_checksum($iid) {

		$r = $this->db->query(sprintf("SELECT * FROM images WHERE img_id='%s'",$iid));
		$ret = $r->fetchObject(); $r = null;
				
		if(is_object($ret) && $ret->img_id) {
			$this->db->query(sprintf(
				"UPDATE images SET img_sum='%s' WHERE img_id='%d'",
				md5_file($ret->img_path),
				$ret->img_id
			));
			return true;
		} else {
			return false;
		}		
		
	}	
	

	/*//. query functions
	../ -------------------------- .//*/
	
	function image_exists($iid) {
		$r = $this->db->query(sprintf("SELECT img_id FROM images WHERE img_path='%d'",$iid));
		$ret = $r->fetchObject(); $r = null;	

		if(is_object($ret) && $ret->img_id) {
			if(md5_file($ret->img_path) != $ret->img_sum) {
				return -1;
			} else {
				return $ret->img_id;
			}
		} else {
			return false;
		}
	}
	
	function image_path_exists($path) {
	
		$r = $this->db->query(sprintf("SELECT img_id FROM images WHERE img_path='%s'",addslashes($path)));
		$ret = $r->fetchObject(); $r = null;
		
		if(is_object($ret) && $ret->img_id) {
			return $ret->img_id;
		} else {
			return false;
		}
	}
	
	function image_tag_exists($iid,$tid) {
		
		if($this->tagcache[$iid][$tid]) {
			return $this->tagcache[$iid][$tid];
		}	
	
		$r = $this->db->query(sprintf("SELECT img_id FROM tag_link WHERE img_id='%d' AND tag_id='%d'",$iid,$tid));
		$ret = $r->fetchObject(); $r = null;
		
		if(is_object($ret) && $ret->img_id) {
			$this[$iid][$tid] = true;
		} else {
			$this[$iid][$tid] = false;
		}
		
		return $this->tagcache[$iid][$tid];
	}
	
	function get_image_info($iid) {
	
		$r = $this->db->query(sprintf(
			"SELECT * FROM images NATURAL JOIN info WHERE images.img_id='%d'",
			$iid
		));

		$ret = $r->fetchObject(); $r = null;		
		
		if(is_object($ret)) {
			return $ret;
		} else {
			return false;
		} 
	
	}
		
	function get_image_info_by_path($path) {
	
		$r = $this->db->query(sprintf("SELECT img_id FROM images WHERE img_path='%s'",addslashes($path)));
		$ret = $r->fetchObject(); $r = null;
		
		if(is_object($ret) && $ret->img_id) {
			return $this->get_image_info($ret->img_id);
		} else {
			return false;
		} 
	}
	
	function get_image_tags($iid) {
		$r = $this->db->query(sprintf("SELECT * FROM tag_link NATURAL JOIN tag WHERE img_id='%d'",$iid));
		$ret = $r->fetchAll(PDO::FETCH_CLASS); $r = null;

		foreach($ret as $id => $data) {
		
			if($data->tag_thm_data) {
				$tmp = tempnam("",".imagethumb-");
				file_put_contents($tmp,$ret->thm_data);
				if(filesize($tmp)) {
					$buf = GdkPixbuf::new_from_file($tmp);
				} unlink($tmp);
				$ret[$id]->tag_thm_pixbuf = $buf;
			} else {
				$ret[$id]->tag_thm_pixbuf = null;
			}
		
		}
		return $ret;
	}
	
	function get_thumbnail($iid) {
		$r = $this->db->query(sprintf("SELECT * FROM thumbnails WHERE img_id='%d'",$iid));
		$ret = $r->fetchObject(); $r = null;

		if(is_object($ret) && $ret->thm_data) {

			self::build_pixbuf_from_data($ret->thm_data,$buf);
					
			return $buf;
		} else {
			return null;
		} 
	}
	
	function get_tag_categories() {
		$r = $this->db->query("SELECT * FROM tag_category ORDER BY cat_name ASC");
		$ret = $r->fetchAll(PDO::FETCH_CLASS); $r = null;

		if(is_array($ret) && count($ret) > 0) {
			return $ret;
		} else {
			return array();
		}
	}
	
	function get_tag_category($cat_id) {
		$r = $this->db->query(sprintf("SELECT * FROM tag_category WHERE cat_id='%d'",$cat_id));
		$ret = $r->fetchAll(PDO::FETCH_CLASS); $r = null;

		if(is_object($ret) && $ret->cat_id) {
			return $ret;
		} else {
			return null;
		} 
	}


	function get_image_all(&$output,$thm) {
		$r = $this->db->query("SELECT * FROM images NATURAL JOIN info");
		
		$output = $r->fetchAll(PDO::FETCH_ASSOC); $r = null;
		
		if($thm === true) {
			foreach($output as $image => $data) {
				$output[$image]['thm_pixbuf'] = $this->get_thumbnail($output[$image]['img_id']);
			}
		}
		
	}



}

?>