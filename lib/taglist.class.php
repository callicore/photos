<?php
/**
 * taglist.class.php - treeview for tags
 *
 * tags to drop onto pictures and sort by
 *
 * This is released under the GPL, see docs/gpl.txt for details
 *
 * @author       Elizabeth M Smith <emsmith@callicore.net>
 * @copyright    Elizabeth M Smith (c)2006
 * @link         http://callicore.net/desktop/programs/writer
 * @license      http://www.opensource.org/licenses/gpl-license.php GPL
 * @version      $Id: taglist.class.php 257 2007-01-28 17:36:51Z emsmith $
 * @since        Php 5.2.0
 * @package      callicore
 * @subpackage   photo
 * @category     lib
 * @filesource
 */

/**
 * CC_Taglist - tag treeview
 *
 * Drag a tag onto an image
 */
class CC_Taglist extends GtkTreeView
{
	protected $liststore;
	public $map = array();
	public $count = 0;

	/**
	 * public function __construct
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function __construct($db)
	{
		$this->db = $db;
		//create and populate the list store
		$store = $this->liststore = new GtkTreeStore(Gtk::TYPE_STRING, //name
													 GdkPixbuf::gtype, //pixbuf
													 Gtk::TYPE_BOOLEAN, //filtered
													 Gtk::TYPE_LONG// id
													 );
		parent::__construct($this->liststore);

		$map = $this->map = array();

		// populate categories first
		$window = new GtkWindow();

		$cats = $db->get_tag_categories();
		foreach($cats as $data)
		{
			$parent = $store->append(null, array($data->cat_name, null, false, $data->cat_id));
			$map[$store->get_string_from_iter($parent)] = false;
			$tags = $db->get_image_tags($data->cat_id);
			foreach($tags as $tag)
			{
				if(is_null($tag->tag_thm_pixbuf))
				{
					$tag->tag_thm_pixbuf = $window->render_icon($data->cat_stock, CC::$MENU);
				}
				$iter = $store->append($parent, array($tag->tag_word, $tag->tag_thm_pixbuf, false, $tag->tag_id));
				$map[$store->get_string_from_iter($iter)] = false;
			}
		}

$this->connect('button-press-event', array($this, 'on_button_press_event'), $this);
$this->drag_source_set(Gdk::BUTTON1_MASK,
				array(array('text/plain', 0, 0)), Gdk::ACTION_COPY|Gdk::ACTION_MOVE);
$this->connect('drag-data-get', array($this, 'on_drag'));

$filter = new GtkCellRendererToggle();
$filter->set_property('activatable', true);
$filter->connect('toggled', array($this, 'on_filter'));

//cell renderer, which actually displays the text
$this->append_column(new GtkTreeViewColumn('Show', $filter, 'active', 2));
//append one column to the view which displays the tree store column 0
$this->append_column(new GtkTreeViewColumn('Cat', new GtkCellRendererText(), 'text', 0));
//display column 1 from the tree store
$this->append_column(new GtkTreeViewColumn('Tag', new GtkCellRendererPixbuf(), 'pixbuf', 1));
//show all children at once
$this->expand_all();

		$menu = $this->menu = new GtkMenu();
		$actions = CC_Actions::instance();

		$menu->append($actions->create_menu_item('tag', 'delete'));

		$menu->show_all();

		$this->connect_simple('popup-menu', array($this, 'popup'));


	}

function on_filter($renderer, $row)
{
	$model = $this->get_model();
    $iter = $model->get_iter($row);
	$value = !$model->get_value($iter, 2);
    //The value has been toggled -> we need
    // to invert the current value
    $model->set(
        $iter,
        2,
        $value
    );
	$this->map[$model->get_string_from_iter($iter)] = $value;
	if($value == true)
	{
		$this->count++;
	}
	else
	{
		$this->count--;
	}
}

function get_filters()
{
	return $this->map;
}

// process drag
function on_drag($widget, $context, $data, $info, $time) {
    $selection = $widget->get_selection();
    list($model, $iter) = $selection->get_selected();
    if ($iter==null) return;
    $data->set_text($model->get_value($iter, 3));  // note 5
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
	 * public function repopulate
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function repopulate()
	{
		$store = $this->liststore;
		$store->clear();
		$db = $this->db;
		$map = $this->map = array();

		// populate categories first
		$window = new GtkWindow();

		$cats = $db->get_tag_categories();
		foreach($cats as $data)
		{
			$parent = $store->append(null, array($data->cat_name, null, false, $data->cat_id));
			$map[$store->get_string_from_iter($parent)] = false;
			$tags = $db->get_image_tags($data->cat_id);
			foreach($tags as $tag)
			{
				if(is_null($tag->tag_thm_pixbuf))
				{
					$tag->tag_thm_pixbuf = $window->render_icon($data->cat_stock, CC::$MENU);
				}
				$iter = $store->append($parent, array($tag->tag_word, $tag->tag_thm_pixbuf, false, $tag->tag_id));
				$map[$store->get_string_from_iter($iter)] = false;
			}
		}
	}
}
?>