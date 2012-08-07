<?php
/**
 * photo.class.php - Main window for the photo program
 *
 * main window for the application, should display a nice treeview of all items
 *
 * This is released under the GPL, see docs/gpl.txt for details
 *
 * @author       Elizabeth M Smith <emsmith@callicore.net>
 * @copyright    Elizabeth M Smith (c)2006
 * @link         http://callicore.net/desktop/programs/writer
 * @license      http://www.opensource.org/licenses/gpl-license.php GPL
 * @version      $Id: photo.class.php 265 2007-01-28 17:56:56Z emsmith $
 * @since        Php 5.2.0
 * @package      callicore
 * @subpackage   photo
 * @category     lib
 * @filesource
 */

/**
 * CC_Photo - checks settings and manages common properties
 *
 * Basically a wrapper class for the application
 */
class CC_Photo extends CC_Main
{

	/**
	 * website url to open
	 * @var $website string
	 */
	protected $website = 'http://gtkmw.blogture.com/';
	protected $list_style = 'icon';
	protected $scroll;
	public $taglist;
	protected $tagscroll;
	public $db;
	/**
	 * menu bar definition
	 * @var $menubar array
	 */
	protected $menubar = array(
	'_File' => array(
	'file:import',
	'file:search',
	'separator',
	'file:quit',
	),
	'_View' => array(
	'toolbar:toggle',
	'view:fullscreen',
	'view:thumbnail',
	'view:details',
	),
	'_Help' => array(
	'help:help',
	'help:website',
	'separator',
	'help:about',
	),
	);

	/**
	 * default toolbar layout
	 * @var $tooldefault array
	 */
	protected $tooldefault = array(
	'file:import',
	'file:search',
	//'file:tag',
	'separator',
	'view:thumbnail',
	'view:details',
	'view:fullscreen',
	);

	protected $view;

	/**
	 * public function __construct
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function __construct()
	{
		CC::icon('cc-camera');
		// start splash window while we set up the interface and load images
		$splash = new CC_Splash(4, 'Photo Manager');
		$splash->set_image(CC::$dir . 'programs' . DS . 'photo' . DS . 'splash.png');
		$splash->set_license();
		$splash->set_copyright('GtkMerryWizards (c) 2006');
		$splash->set_version('1.0-phpthrowdown');
		$splash->show_all();
		$splash->update('Loading Database');
		$this->db = new CC_Db(CC::$dir . 'db' . DS . 'images.db');

		//parent window constructor
		parent::__construct();
		$this->set_title('Manager View', true);

		$splash->update('Creating UI');
		//paned area with frame for deliniation
		$pane = new GtkHPaned();
		$pane->set_border_width(5);
		$left = new GtkFrame();
		$left->set_shadow_type(Gtk::SHADOW_IN);
		$pane->pack1($left, true, true);
/* Tags removed due to general bugginess
		$right = new GtkFrame();
		$right->set_shadow_type(Gtk::SHADOW_IN);
		$pane->pack2($right, true, false);
*/
		// scroll and image listing
		$this->scroll = new GtkScrolledWindow();
		$this->scroll->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
		$left->add($this->scroll);
		$this->vbox->add($pane);

/* Tags removed due to general bugginess
		$splash->update('Loading Tags');
		$this->taglist = new CC_Taglist($this->db);
*/
		$splash->update('Populating Images');
		$this->photolist = new CC_Photolist($this->db, $this->taglist);
		$config = CC_Config::instance();
		$type = isset($config->photo->list_style) ? $config->photo->list_style : 'icon';
		if($type == 'icon')
		{
			$this->scroll->add($this->photolist->get_iconview());
		}
		else
		{
			$this->scroll->add($this->photolist->get_treeview());
		}
/* Tags removed due to general bugginess
		// right area is a treeview with tags
		$this->tagscroll = new GtkScrolledWindow();
		$this->tagscroll->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
		$right->add($this->tagscroll);
		$this->tagscroll->add($this->taglist);
*/
		$config = CC_Config::instance();
		$actions = CC_Actions::instance();
		$list = isset($config->photo->list_style) ? $config->photo->list_style : 'icon';
		if($list == 'icon')
		{
			$action = $actions->get_action('view', 'thumbnail')->set_active(true);
		}
		else
		{
			$action = $actions->get_action('view', 'details')->set_active(true);
		}

		// show it all and destroy the splash
		$this->show_all();
		$splash->destroy();

	}

	/**
	 * protected function register_actions
	 *
	 * creates generic window actions
	 *
	 * @todo add additional generic actions
	 * @return void
	 */
	protected function register_actions()
	{
		parent::register_actions();
		$actions = CC_Actions::instance();

		$actions->add_actions('file', array(
		array(
		'type' => 'action',
		'name' => 'import',
		'label' => '_Import...',
		'short-label' => '_Import',
		'tooltip' => 'Add Items...',
		'callback' => array($this, 'on_import'),
		'image' => 'gtk-convert',
		),
		array(
		'type' => 'action',
		'name' => 'search',
		'label' => '_Search...',
		'short-label' => '_Search',
		'tooltip' => 'Search for items...',
		'callback' => array($this, 'on_search'),
		'image' => 'gtk-find',
		),
		array(
		'type' => 'action',
		'name' => 'tag',
		'label' => '_Tag',
		'short-label' => '_Tag',
		'tooltip' => 'Create new tags',
		'callback' => array($this, 'on_tag'),
		'image' => 'cc-bookmark',
		),)
		);

		$actions->add_actions('editor', array(
		array(
		'type' => 'action',
		'name' => 'edit',
		'label' => '_Edit...',
		'short-label' => '_Edit',
		'tooltip' => 'Edit Item...',
		'callback' => array($this, 'on_edit'),
		'image' => 'gtk-convert',
		),
		array(
		'type' => 'action',
		'name' => 'delete',
		'label' => '_Delete',
		'short-label' => '_Delete',
		'tooltip' => 'Delete Item',
		'callback' => array($this, 'on_delete_confirm'),
		'image' => 'gtk-edit',
		))
		);

		$actions->add_actions('tag', array(
		array(
		'type' => 'action',
		'name' => 'delete',
		'label' => '_Delete',
		'short-label' => '_Delete',
		'tooltip' => 'Delete Item',
		'callback' => array($this, 'on_tagdelete_confirm'),
		'image' => 'gtk-edit',
		))
		);
	
		$actions->add_actions('view', array(
		array(
		'type' => 'radio',
		'name' => 'thumbnail',
		'label' => '_Thumbnail',
		'short-label' => '_Thumbnail',
		'tooltip' => 'View items in thumbnail mode',
		'callback' => array($this, 'on_thumbnail'),
		'value' => 1,
		'image' => 'cc-view-icon',
		'radio' => 'thumbnail',
		),
		array(
		'type' => 'radio',
		'name' => 'details',
		'label' => '_Details',
		'short-label' => '_Details',
		'tooltip' => 'View items in detail mode',
		'image' => 'cc-view-text',
		'callback' => array($this, 'on_list'),
		'value' => 2,
		'radio' => 'thumbnail',
		),
		));
		return;
	}

	public function on_import()
	{
		$dialog = new GtkFileChooserDialog(CC::i18n('%s :: %s', CC::$program, 'Select Images'),
		CC::$window, Gtk::FILE_CHOOSER_ACTION_OPEN);
		$preview = new GtkImage();

		//dialog settings
		$dialog->set_preview_widget($preview);
		$dialog->connect('update-preview', array($this, 'on_update_preview'), $preview);
		$dialog->set_select_multiple(true);

		//treestore to hold items we're adding
		$extra = new GtkVBox();
		$scroll = new GtkScrolledWindow();
		$scroll->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
		$extra->add($scroll);
		$liststore = new GtkListStore(Gtk::TYPE_STRING);
		$treeview = new GtkTreeView($liststore);
		$treeview->append_column(new GtkTreeViewColumn('File', new GtkCellRendererText(), 'text', 0));
		$scroll->add($treeview);

		//buttons
		$dialog->set_extra_widget($extra);
		$button = $dialog->add_button('Add Image', Gtk::RESPONSE_APPLY);
		$button->connect_simple('clicked', array($this, 'on_import_add_filename'), $dialog, $liststore);
		$button = $dialog->add_button('Import', Gtk::RESPONSE_OK);
		//		$button->connect('clicked', array($this, 'on_import_add_to_db'),$liststore);
		$dialog->add_button(Gtk::STOCK_CLOSE, Gtk::RESPONSE_CLOSE);
		$dialog->show_all();
		$run = true;
		while($run) {
			$result = $dialog->run();
			switch($result) {
				case(Gtk::RESPONSE_APPLY): {
					//. callback did the work already.
					break;
				}
				case(Gtk::RESPONSE_OK): {
					$run = false;
					$dialog->set_sensitive(false);
					while(gtk::events_pending()) { gtk::main_iteration(); }
					$this->on_import_add_to_db($dialog,$liststore);
					$this->reload_library();
					break;
				}
				case(Gtk::RESPONSE_CLOSE): {
					$run = false;
					break;
				}
			}

			if(!$run) { break; }
		}

		$dialog->destroy();
	}

	/**
	 * public function on_update_preview
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_update_preview($chooser, $preview)
	{
		$filename = $chooser->get_preview_filename();
		try
		{
			$pixbuf = GdkPixbuf::new_from_file($filename);
			$width = $pixbuf->get_width();
			$height = $pixbuf->get_height();
			if($height > 128 || $width > 128)
			{
				if ($width > $height)
				{
					$height = $height * ( 128/$width );
					$width = 128;
				}
				elseif ($width < $height)
				{
					$width = $width * ( 128/$height );
					$height = 128;
				}
				else
				{
					$width = 128;
					$height = 128;
				}
				$pixbuf = GdkPixbuf::new_from_file_at_size($filename, $height, $width);
			}
			$preview->set_from_pixbuf($pixbuf);
			$have_preview = true;
		}
		catch(Exception $e)
		{
			$have_preview = false;
		}
		$chooser->set_preview_widget_active($have_preview);
	}

	/**
	 * public function on_import_add_filename
	 *
	 * adds a filename to the treestore when import is clicked
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_import_add_filename($chooser, $store)
	{
		$files = $chooser->get_filenames();
		foreach($files as $name)
		{
			if(!is_file($name))
			{
				continue;
			}
			$data = getimagesize($name);
			if ($data > 0)
			{
				$store->append(array($name));
			}
		}
	}

	public function is_image_type($path)
	{
		list($width,$height,$type) = getimagesize($path);
		if($type > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function aspect_scale($path) {
		list($width,$height) = getimagesize($path);
		if($width == $height) {
			return array(50,50);
		}
		if($width > $height) {
			$h = ((50 * $height) / $width);
			return array(50,$h);
		}
		if($width < $height) {
			$w = ((50 * $width) / $height);
			return array($w,50);
		}
	}

	public function on_import_add_to_db($dialog, $store)
	{

		$qdia = new GtkDialog;
		$qdia->p = new GtkProgressBar;
		$qdia->v = new GtkVBox;
		$qdia->set_title('Importing...');
		$qdia->set_size_request(300,50);
		$qdia->vbox->pack_start($qdia->p,true,true,3);
		$qdia->set_transient_for($dialog);
		$qdia->set_modal(true);
		$qdia->show_all();

		while(gtk::events_pending()) { gtk::main_iteration(); }

		for($a = 0; $a < count($store); $a++) {
			$path = $store->get_value($store->get_iter($a),0);
			if($this->is_image_type($path)) {
				$qdia->p->set_text(sprintf("%d of %d",($a+1),count($store)));
				$iid = $this->db->put_image($path);
				$pixbuf = GdkPixbuf::new_from_file($path);
				$tmpbuf = ImageManipulation::new_from_pixbuf($pixbuf);
				$aspect = CC_Photo::aspect_scale($path);
				$tmpthm = $tmpbuf->get_thumb($aspect[0],$aspect[1]);
				$this->db->put_thumbnail($iid,$tmpthm->get_gd());
				$pixbuf = $tmpbuf = $tmpthm = null;
				$qdia->p->set_fraction(($a + 1) / count($store));
				while(gtk::events_pending()) { gtk::main_iteration(); }
			}
		}

		$qdia->destroy();
	}

	public function on_about()
	{
		GtkAboutDialog::set_url_hook(array($this, 'hook'));
		GtkAboutDialog::set_email_hook(array($this, 'hook'));
		$dialog = new GtkAboutDialog();
		$dialog->set_position(Gtk::WIN_POS_CENTER);
		$dialog->set_title("Photo Manager");
		$dialog->set_version("0.1");
		$dialog->set_copyright("GtkMerryWizards (c) 2006");
		$dialog->set_website("http://gtkmw.blogture.com");
		$dialog->set_website_label("GtkMerryWizards' Blog");
		$dialog->set_license(file_get_contents(CC::$dir . DS . 'docs' . DS . 'gpl.txt'));
		$dialog->set_logo(GdkPixbuf::new_from_file(CC::$dir."programs".DS."photo".DS."about.PNG"));
		$dialog->set_authors(array(
			"Elizabeth M Smith <auroraeosrose@gmail.com>",
			"Bob Majdak Jr ",
			"Leon Pegg <leon.pegg@gmail.com>",
			"Jace Ferguson"
		));
		$dialog->set_comments("This application was built for the phpthrowdown, a contest to build the best app possible in 24 hours. In it's current state this most likely should be considered incomplete, and not production quality... yet.");
		$dialog->run();
		$dialog->destroy();	
	
	}
	public function on_help()
	{
		$dialog = new GtkDialog('Basic Help');
		$dialog->scroll = new GtkScrolledWindow;
		$dialog->view = new GtkTextView;
		$dialog->buffer = new GtkTextBuffer;
		$dialog->close = new GtkButton('Close');
		
		$dialog->set_position(Gtk::WIN_POS_CENTER);
		$dialog->set_size_request(400,400);
		$dialog->scroll->set_policy(Gtk::POLICY_AUTOMATIC,Gtk::POLICY_AUTOMATIC);
		$dialog->view->set_wrap_mode(3);
		$dialog->view->set_cursor_visible(false);
		$dialog->view->set_editable(false);
		$dialog->close->connect_simple('clicked',array($dialog,'response'),Gtk::RESPONSE_OK);
		
		$dialog->buffer->insert_at_cursor("Basic help for Photo Manager.

The manage photos UI allows for categorization and organization of images on your computer. You may import photos one file at a time or with the search feature which will locate all images in a given directory.

There are also several image manipulation tasks which may be performed such as blur and contrast control.

The application is still missing several features as it was built for the phpthrowdown 24 hour contest.
");

		$dialog->scroll->add($dialog->view);
		$dialog->view->set_buffer($dialog->buffer);
		
		$dialog->vbox->pack_start($dialog->scroll,TRUE,TRUE,3);
		$dialog->vbox->pack_start($dialog->close,FALSE,FALSE,3);
		
		$dialog->show_all();
		$dialog->run();
		$dialog->destroy();
	}

	public function on_delete_confirm()
	{
		$path = $this->photolist->last_path;
		$dialog = new CC_Message('Are you sure you want to delete this file?',
		'Deleting from Library', CC_Message::QUESTION);
		$dialog->show_all();
		$response = $dialog->run();
		if ($response == Gtk::RESPONSE_YES) {
			$iter = $this->photolist->filter->get_iter($path);
			$iter = $this->photolist->filter->convert_iter_to_child_iter($iter);
			$file = $this->photolist->liststore->get_value($iter, 3);
			$id = $this->photolist->liststore->get_value($iter, 2);
			$this->photolist->liststore->remove($iter);
			$this->db->drop_image($id);
		}
		$dialog->destroy();
	}

	public function on_tagdelete_confirm()
	{
		$path = $this->taglist->last_path;
		$iter = $this->taglist->get_model()->get_iter($path);
		$dialog = new CC_Message('Are you sure you want to delete this tag?',
		'Deleting from Tags', CC_Message::QUESTION);
		$dialog->show_all();
		$response = $dialog->run();
		if ($response == Gtk::RESPONSE_YES) {
			$id = $this->taglist->get_value($iter, 2);
			$this->taglist->remove($iter);
			$this->db->drop_tag($id);
		}
		$dialog->destroy();
	}

	/**
	 * public function on_thumbnail
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_thumbnail()
	{
		// replace treeview with iconview
		$child = $this->scroll->get_child();
		$this->scroll->remove($child);
		$this->scroll->add($this->photolist->get_iconview());
		$config = CC_Config::instance();
		$config->photo->list_style = $this->list_style = 'icon';
		$this->show_all();
	}

	/**
	 * public function on_thumbnail
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_list()
	{
		// replace treeview with iconview
		$child = $this->scroll->get_child();
		$this->scroll->remove($child);
		$this->scroll->add($this->photolist->get_treeview());
		$config = CC_Config::instance();
		$config->photo->list_style = $this->list_style = 'detail';
		$this->show_all();
	}

	/**
	 * public function on_activate_item
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_edit()
	{
		$path = $this->photolist->last_path;
		$iter = $this->photolist->filter->get_iter($path);
		$iter = $this->photolist->filter->convert_iter_to_child_iter($iter);
		$file = $this->photolist->liststore->get_value($iter, 3);
		$id = $this->photolist->liststore->get_value($iter, 2);
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
					$this->photolist->liststore->remove($iter);
					$this->db->drop_image($id);
					$dialog->destroy();
					return;
				}
				else
				{
					$this->db->update_file_info($id, array(CC_Db::INFO_PATH => $file));
					$this->photolist->liststore->set($iter, 3, $file);
				}
			}
			else
			{
				$this->photolist->liststore->remove($iter);
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
	 * public function on_search
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_search()
	{
		$dialog = new GtkFileChooserDialog(CC::i18n('Search for Images'), $this, Gtk::FILE_CHOOSER_ACTION_SELECT_FOLDER, array(Gtk::STOCK_OK, Gtk::RESPONSE_OK), null);
		$dialog->show_all();
		if ($dialog->run() == Gtk::RESPONSE_OK) {
			$selected_file = $dialog->get_filename();
			//echo "selected_file = $selected_file\n";
			$dialog->destroy();
			$search = new ImageSearch();
			$search->search($selected_file);
			$this->reload_library();
		}else{
			$dialog->destroy();
		}
	}

	public function reload_library(){
		$this->photolist = new CC_Photolist($this->db, $this->taglist);
		if (get_class($this->scroll->get_child()) == 'GtkIconView') {
			$this->on_thumbnail();
		}else{
			$this->on_list();
		}
	}

	/**
	 * public function on_quit
	 *
	 * exits the program
	 *
	 * @return void
	 */
	public function hook($item)
	{
		if(!stristr($item, 'http://'))
		{
			$item = 'mailto:' . $email;
		}
		CC_Os::instance()->launch($item);
		return;
	}

	/**
	 * public function on_quit
	 *
	 * exits the program
	 *
	 * @return void
	 */
	public function on_quit()
	{
		$config = CC_Config::instance();
		if (!isset($config->photo))
		{
			$config->photo = new StdClass();
		}
		$config->photo->list_style = $this->list_style;
		$this->db->shutdown();
		return;
	}

	/**
	 * public function on_tag
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function on_tag()
	{
		$win = new CC_Phototagedit();
		$win->show_all();
	}
}
?>
