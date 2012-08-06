<?php
/**
 * photolist.class.php - Iconview for photos
 *
 * major portion of the application
 *
 * This is released under the GPL, see docs/gpl.txt for details
 *
 * @author       Elizabeth Smith <emsmith@callicore.net>
 * @copyright    Elizabeth Smith (c)2006
 * @link         http://callicore.net/desktop/programs/writer
 * @license      http://www.opensource.org/licenses/gpl-license.php GPL
 * @version      $Id: photolist.class.php 258 2007-01-28 17:46:54Z emsmith $
 * @since        Php 5.2.0
 * @package      callicore
 * @subpackage   photo
 * @category     lib
 * @filesource
 */

/**
 * CC_Photolist - main iconview
 *
 * Need to allow dragging images on/off
 */
class CC_Photolist
{

	public $liststore;
	public $filter;
	protected $iconview;
	protected $treeview;
	protected $taglist;

	/**
	 * public function __construct
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function __construct($db, $taglist)
	{
		$this->db = $db;
		$this->taglist = $taglist;

		//construct and populate the list store
		$this->liststore = new GtkListStore(GdkPixbuf::gtype, // thumbnail
											Gtk::TYPE_STRING, // name
											Gtk::TYPE_LONG,   //id
											Gtk::TYPE_STRING,   //file
											Gtk::TYPE_STRING // date
											);
		$this->iconview = new GtkIconView();
		$this->iconview ->set_pixbuf_column(0);
		$this->iconview ->set_text_column(1);
		$this->iconview ->set_selection_mode(Gtk::SELECTION_MULTIPLE);
		$this->iconview ->connect('item-activated', array($this, 'on_activate_item'));
		$this->iconview->connect('button-press-event', array($this, 'on_button_press_event'), $this->iconview);
		//$this->iconview->drag_dest_set(Gtk::DEST_DEFAULT_ALL,
		//	array( array('text/plain', 0, 0)), Gdk::ACTION_COPY|Gdk::ACTION_MOVE);
		//$this->iconview->connect('drag-data-received', array($this, 'on_drop'));

		$this->treeview = new GtkTreeView();
		$this->treeview->append_column($col = new GtkTreeViewColumn('Name', $cell = new GtkCellRendererText(), 'text', 1));
		$cell->set_property('editable', true);
		$cell->connect("edited", array($this, "on_edit_done"), $this->treeview);
		$this->treeview->append_column(new GtkTreeViewColumn('File', new GtkCellRendererText(), 'text', 3));
		$this->treeview->append_column(new GtkTreeViewColumn('Date', new GtkCellRendererText(), 'text', 4));
		$this->treeview ->connect('row-activated', array($this, 'on_activate_item'));
		$this->treeview->connect('button-press-event', array($this, 'on_button_press_event'), $this->treeview);
		//$this->treeview->drag_dest_set(Gtk::DEST_DEFAULT_ALL,
		//	array( array('text/plain', 0, 0)), Gdk::ACTION_COPY|Gdk::ACTION_MOVE);
		//$this->treeview->connect('drag-data-received', array($this, 'on_drop'));

		$menu = $this->menu = new GtkMenu();
		$actions = CC_Actions::instance();

		$menu->append($actions->create_menu_item('editor', 'edit'));
		$menu->append($actions->create_menu_item('editor', 'delete'));

		$menu->show_all();

		$this->treeview->connect_simple('popup-menu', array($this, 'popup'));
		$this->iconview->connect_simple('popup-menu', array($this, 'popup'));

		//real population!
		$list = array();
		$db->get_image_all($list,true);
		foreach($list as $id => $data) {
			while(Gtk::events_pending()) Gtk::main_iteration();
			if ($data['thm_pixbuf'] == null) {
				$pixbuf = gdkpixbuf::new_from_file($data['img_path']);
				//echo $data['img_path']."\n";
				$tmpthumb = ImageManipulation::new_from_pixbuf($pixbuf);
				$tmpthumb2 = $tmpthumb->get_thumb(70);
				$pixbuf2 = $tmpthumb2->get_pixbuf();
				$db->put_thumbnail($data['img_id'],$tmpthumb2->get_gd());
				//$pixbuf2 = $tmpthumb2->get_pixbuf();
			}else{
				$pixbuf2 = $data['thm_pixbuf'];
			}
			$this->liststore->append(array($pixbuf2,$data['i_name'],$data['img_id'],$data['img_path'],date("m/d/y", $data['i_date'])));
		}
		
		//we'll filter the liststore according to the tags provided
		$this->filter = new GtkTreeModelFilter($this->liststore);

		$this->filter->set_visible_func(array($this, 'check_selected_keywords'));
		$this->iconview->set_model($this->filter);
		$this->treeview->set_model($this->filter);
	}

	public function get_iconview(){
		return $this->iconview;
	}

	/**
	 * public function on_activate_item
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_activate_item($view, $path)
	{
		$iter = $this->filter->get_iter($path);
		$iter = $this->filter->convert_iter_to_child_iter($iter);
		$file = $this->liststore->get_value($iter, 3);
		$id = $this->liststore->get_value($iter, 2);
		if(!file_exists($file))
		{
			$dialog = new GtkFileChooserDialog('Missing File', $this, Gtk::FILE_CHOOSER_ACTION_OPEN,
							array(Gtk::STOCK_OK, GTK::RESPONSE_OK, Gtk::STOCK_DELETE, Gtk::RESPONSE_NO));
			$dialog->set_extra_widget(new GtkLabel('The image file is missing - you may browse for it or hit delete to remove it from your library'));
			$response = $dialog->run();
			if($response !== Gtk::RESPONSE_NO)
			{
				$file = $dialog->get_filename;
				if(!file_exists($file) || !is_file($file) || getimagesize($file) > 0)
				{
					$this->liststore->remove($iter);
					$this->db->drop_image($id);
					$dialog->destroy();
					return;
				}
				else
				{
					$this->db->update_file_info($id, array(CC_Db::INFO_PATH => $file));
					$this->liststore->set($iter, 3, $file);
				}
			}
			else
			{
				$this->liststore->remove($iter);
				$this->db->drop_image($id);
				$dialog->destroy();
				return;
			}
			$dialog->destroy();
			
			return;
		}
		$editor = new ImageEditor($file, $id);
		$editor->show_all();
	}

	/**
	 * public function on_activate_item
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function get_treeview()
	{
		return $this->treeview;
	}

	/**
	 * public function popup
	 *
	 * pops up a context menu on right click
	 *
	 * @return void
	 */
	public function popup()
	{
		$this->menu->popup();
		return TRUE;
	}

	/**
	 * public function on_button_press_event
	 *
	 * pops up a popup menu on right click
	 *
	 * @param object $window GtkWindow
	 * @param object $event GdkEvent
	 * @return void
	 */
	public function on_button_press_event($widget, $event, $view)
	{
		if ($event->button == 3)
		{
			$this->last_path = $view->get_path_at_pos($event->x, $event->y);
			$this->popup();
		}
		return;
	}

	/**
	 * public function on_activate_item
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function check_selected_keywords($model, $iter)
	{
		return true;
		if($this->taglist->count == 0)
		{
			return true;
		}
		$filters = $this->taglist->get_filters();
		foreach($filters as $string_iter => $state)
		{
			if($state == true)
			{
				$new_iter = $this->taglist->get_iter_from_string($string_iter);
				$tag_id = $this->taglist->get_value($new_iter, 3);
				$id = $this->liststore->get_value($this->filter->convert_iter_to_child_iter($iter), 2);
			}
			return $this->db->image_tag_exists($id, $tag_id);
		}
		return true;
	}

public function on_edit_done($cell, $path, $new_text, $view){
    $model=$view->get_model();
    $iter = $model->get_iter_from_string($path);
	$iter = $model->convert_iter_to_child_iter($iter);
	$this->liststore->set($iter, 1, $new_text);
	$id = $this->liststore->get_value($iter, 1);
	$this->db->update_image_info($id, array(CC_Db::INFO_NAME => $new_text));
}

// process drop
function on_drop($widget, $context, $x, $y, $data, $info, $time) {
	$iter = $widget->get_path_at_pos($x, $y);
	if($widget instanceof GtkTreeView)
	{
		$iter = $iter[0];
	}
	$iter = $widget->get_model()->get_iter($iter);
	if(is_null($iter))
	   return;
	// add tag here
	$tag_id = $data->data;
	$id = $widget->get_model()->get_value($iter, 2);
	$this->db->add_tag($id,$tag_id);
	$widget->emit_stop_by_name('drag-data-received');
} 
}
?>