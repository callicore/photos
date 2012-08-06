<?php

/**
 * class to search for image files
 *
 */
class ImageSearch{

	/**
	 * is searching
	 *
	 * @var bool
	 */
	protected $search = false;
	/**
	 * search in progress dialog
	 *
	 * @var GtkDialog
	 */
	protected $dialog;
	/**
	 * Progress bar
	 *
	 * @var GtkProgressbar
	 */
	protected $progress;

	/**
	 * class constructor
	 *
	 */
	public function __construct(){
		$this->build_dialog();
	}

	/**
	 * search function
	 *
	 * @param string $path
	 * @return array
	 */
	public function search($path){
		$this->dialog->show_all();
		$timeout_id = Gtk::timeout_add(500,array($this,'pulse'));
		$this->search = true;
		$found = array();
		if (!$this->gather($path,$found)) {
			$this->dialog->hide_all();
			Gtk::idle_remove($timeout_id);
			return false;
		}else{
			$db = new CC_Db(CC::$dir . 'db' . DS . 'images.db');
			foreach ($found as $image){
				if ($this->search == false) {
					return false;
				}
				while(Gtk::events_pending()) Gtk::main_iteration();
				$iid = $db->image_path_exists($image[1].DIRECTORY_SEPARATOR.$image[0]);
				if ($iid == 0) {
					$iid = $db->put_image($image[1].DIRECTORY_SEPARATOR.$image[0]);
					//$db->update_image_info($iid,array(CC_Db::INFO_PATH => $image[1].DIRECTORY_SEPARATOR.$image[0]));
					$db->update_image_info($iid,array(CC_Db::INFO_NAME => $image[0]));
				}
			}
			$db->shutdown();
			$this->dialog->hide_all();
			Gtk::idle_remove($timeout_id);
			return $found;
		}
	}

	/**
	 * pulses progress bar
	 *
	 * @return bool
	 */
	public function pulse(){
		$this->progress->pulse();
		return true;
	}

	/**
	 * cancels search if one in progress
	 *
	 */
	public function cancel(){
		$this->search = false;
	}

	/**
	 * Builds the search in progress dialog
	 *
	 */
	protected function build_dialog(){
		$dialog = new GtkDialog('Scanning in progress...', null, Gtk::DIALOG_MODAL);
		$top_area = $dialog->vbox;
		$top_area->pack_start(new GtkLabel('Please hold on while processing directory...'));
		$this->progress = new GtkProgressBar();
		$this->progress->set_orientation(Gtk::PROGRESS_LEFT_TO_RIGHT);
		$top_area->pack_start($this->progress, 0, 0);
		$cancel = new gtkbutton('cancel');
		$top_area->pack_start($cancel, false, false);
		$cancel->connect_simple('clicked',array($this,'cancel'));
		$dialog->set_has_separator(false);
		$this->dialog = $dialog;
	}

	/**
	 * recursive method to get file list
	 *
	 * @param string $path
	 * @param array $found
	 * @return array
	 */
	protected function gather($path,&$found){
		$dir = new DirectoryIterator($path);
		foreach ($dir as $file){
			while(Gtk::events_pending()) Gtk::main_iteration();
			if ($file->isFile() & !$file->isDot() & !$file->isDir() & $file->isReadable()) {
					$data = @getimagesize($file->getPath().DIRECTORY_SEPARATOR.$file->getFilename());
					switch ($data[2]){
							case 1 : $found[] = array($file->getFilename(),$file->getPath());break;
							case 2 : $found[] = array($file->getFilename(),$file->getPath());break;
							case 3 : $found[] = array($file->getFilename(),$file->getPath());break;
							case 6 : $found[] = array($file->getFilename(),$file->getPath());break;
					}
			}
			if (!$file->isDot() & $file->isDir()) {
				$this->gather($file->getPath().DIRECTORY_SEPARATOR.$file->getFilename(),$found);
			}
			if ($this->search == false) {
				return false;
			}
		}
		return true;
	}

}

?>