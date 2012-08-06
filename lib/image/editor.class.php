<?php
/*
Image editor written by Jace Ferguson with help from Leon Pegg
*/
/**
 * ImageEditor - Editor dialog for images
 *
 * Image Editor Dialog
 */
class ImageEditor extends CC_Window
{

	protected $edit_pixbuf; //The preview pixbuf
	protected $edit_changes = array(); //Queue of filters to apply to an image
	protected $properties_frame; //Holds the properties
	protected $current_widget; //Widget currently in the properties box
	protected $image; //The preview image
	protected $orig_id; //image id
	protected $orig_gd; //original data to make final filter applies too
	protected $orig_width;
	protected $orig_height;
	protected $file; //The location of the file
	protected $image_info_label; //The image infor label
	/**
	 * public function __construct
	 *
	 * description
	 *
	 * @param type $name about
	 * @return type about
	 */
	public function __construct($file, $id)
	{
		$this->orig_id = $id;

		//Check that file exists
		if (file_exists($file)) {
			$this->file = $file;
			$data = @getimagesize($file);
			switch ($data[2]){
				case 1 : try {$image = ImageEffects::new_from_gif($file);}
				catch(CC_Exception $e)
				{
					$message = new CC_Message($e->getMessage(), 'Exception', CC_Message::WARNING);
					$message->run();
					$message->destroy();
				}
				break;
				case 2 : try {$image = ImageEffects::new_from_jpeg($file);}
				catch(CC_Exception $e)
				{
					$message = new CC_Message($e->getMessage(), 'Exception', CC_Message::WARNING);
					$message->run();
					$message->destroy();
				}break;
				case 3 : try {$image = ImageEffects::new_from_png($file);}
				catch(CC_Exception $e)
				{
					$message = new CC_Message($e->getMessage(), 'Exception', CC_Message::WARNING);
					$message->run();
					$message->destroy();
				}break;
			}
			$this->orig_gd = $image->get_gd();
		}

		//Backup orignail dimensions
		$width = $this->orig_width = $image->get_width();
		$height = $this->orig_height = $image->get_height();

		//Shrink to fit on screen or enlarge to see
		if($width > 800)
		{
			$image = $image->get_thumb(800);
		}
		else if($width < 45)
		{
			$image = $image->get_thumb(45);
		}
		$this->edit_pixbuf = $image->get_pixbuf();

		//parent window constructor
		parent::__construct();
		$this->set_title('Editor', true);
		$this->register_actions();

		//paned area with frame for deliniation
		$pane = new GtkHPaned();
		$pane->set_border_width(5);

		//Toolbar frame
		$left = new GtkFrame();
		$left->set_shadow_type(Gtk::SHADOW_IN);
		$pane->add1($left);

		//Image pane
		$image_pane = new GtkVPaned();
		$image_pane->set_border_width(5);

		//Edit Frame
		$edit_image_frame = new GtkFrame();
		$edit_image_frame->set_shadow_type(Gtk::SHADOW_IN);
		$image_pane->add1($edit_image_frame);
		$pane->add2($image_pane);
		$actions = CC_Actions::instance();

		//Toolbar frame children
		$hbutton_box = new GtkHButtonBox();
		$hbutton_box->set_layout(Gtk::BUTTONBOX_SPREAD);
		$back = $actions->create_button_item('manip', 'return');
		$hbutton_box->add($back);

		//Toolbar _vbox
		$toolbar_vbox = new GtkVBox();
		$toolbar_vbox->pack_start($hbutton_box, false, true, 2);

		//Tools frame
		$toolbar_frame = new GtkFrame();
		$toolbar_frame->set_label('Tools');
		$toolbar_vbox->pack_start($toolbar_frame, false, true, 2);

		//Tool buttons
		$effects_vbutton_box = new GtkVButtonBox();
		$effects_vbutton_box->set_layout(Gtk::BUTTONBOX_SPREAD);
		$effects_vbutton_box->set_spacing(3);
		$effects_vbutton_box->add($actions->create_button_item('manip', 'grayscale'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'negative'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'gblur'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'emboss'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'sketch'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'brighten'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'contrast'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'smooth'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'rotate'));
		$effects_vbutton_box->add($actions->create_button_item('manip', 'resize'));

		//assembly
		$toolbar_frame->add($effects_vbutton_box);
		$this->vbox = new GtkVBox();
		$this->add($this->vbox);
		$this->vbox->add($pane);
		$left->add($toolbar_vbox);

		//Properties Vbox
		$this->properties_frame = new GtkFrame();
		$this->properties_frame->set_label('Properties');
		$toolbar_vbox->pack_start($this->properties_frame, false, true);
		$undo_box = new GtkHButtonBox();
		$undo_box->set_layout(Gtk::BUTTONBOX_SPREAD);
		$undo_box->add($actions->create_button_item('manip', 'undo'));
		$undo_box->add($actions->create_button_item('manip', 'save'));
		$toolbar_vbox->pack_start($undo_box, false, true);

		//Image
		$image_vbox = new GtkVBox();

		//Current Image
		$this->image_info_label = new GtkLabel('Pictures Info');
		$this->image_info_label->set_use_markup(true);
		$image_vbox->pack_start($this->image_info_label, false, true);
		$this->image = new GtkImage();
		$image_vbox->pack_start($this->image, false, true);
		$edit_image_frame->add($image_vbox);

		$this->show_all();

		//Display the image
		$this->set_pixbuf($this->edit_pixbuf);
		//Update the image info
		$this->update_image_info($file, $width, $height);
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
		$actions = CC_Actions::instance();
		$actions->add_action('manip',array
		(
		'type'=>'action',
		'name'=>'resize',
		'label' => '_Resize',
		'short-label' => '_Resize',
		'callback'=>array($this, 'resize_properties'),
		'tooltip'=>'Resize',
		));
		$actions->add_action('manip',
		array
		(
		'type'=>'action',
		'name'=>'return',
		'tooltip'=>'_Return',
		'short-label' => '_Return',
		'label'=>'Return To Library',
		'callback'=>array($this, 'on_return'),
		'image'=> 'gtk-go-back'));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'grayscale',
		'tooltip'=>'_Grayscale',
		'short-label'=>'_Grayscale',
		'label'=>'_Grayscale',
		'callback'=>array($this, 'grayscale_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'negative',
		'tooltip'=>'_Negative',
		'short-label'=>'_Negative',
		'label'=>'_Negative',
		'callback'=>array($this, 'negative_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'gblur',
		'tooltip'=>'_Gaussian Blur',
		'short-label'=>'_Gaussian Blur',
		'label'=>'_Gaussian Blur',
		'callback'=>array($this, 'gaussian_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'emboss',
		'tooltip'=>'_Emboss',
		'short-label'=>'_Emboss',
		'label'=>'_Emboss',
		'callback'=>array($this, 'emboss_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'sketch',
		'tooltip'=>'_Sketch',
		'short-label'=>'_Sketch',
		'label'=>'_Sketch',
		'callback'=>array($this, 'sketch_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'contrast',
		'tooltip'=>'_Contrast',
		'short-label'=>'_Contrast',
		'label'=>'_Contrast',
		'callback'=>array($this, 'contrast_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'brighten',
		'tooltip'=>'_Brighten',
		'short-label'=>'_Brighten',
		'label'=>'_Brighten',
		'callback'=>array($this, 'brighten_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'smooth',
		'tooltip'=>'_Smooth',
		'short-label'=>'_Smooth',
		'label'=>'_Smooth',
		'callback'=>array($this, 'smooth_properties')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'undo',
		'tooltip'=>'_Undo',
		'short-label'=>'_Undo',
		'label'=>'_Undo',
		'callback'=>array($this, 'undo')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'save',
		'tooltip'=>'_Permanently Change',
		'short-label'=>'_Permanently Change',
		'label'=>'_Permanently Change',
		'callback'=>array($this, 'save_changes')
		));
		$actions->add_action('manip',array(
		'type'=>'action',
		'name'=>'cancel',
		'tooltip'=>'_Cancel',
		'short-label'=>'_Cancel',
		'label'=>'_Cancel',
		'callback'=>array($this, 'clear_properties')
		));
		$actions->add_action('manip', array(
		'type'=>'action',
		'name'=>'rotate',
		'tooltip'=>'_Rotate',
		'short-label'=>'_Rotate',
		'label'=>'_Rotate',
		'callback'=>array($this, 'rotate_properties')
		));

		return;
	}
	/**
	 * Display the brighten filter properties
	 *
	 */
	public function brighten_properties()
	{
		$vbox = new GtkVBox();
		$table = new GtkTable(4, 4);
		$scale = new GtkHScale();
		$adjust = new GtkAdjustment(0, -10, 10, .5, 0, 0);
		$scale->set_adjustment($adjust);
		//$scale->connect('format-value', array($this, 'brighten_preview'));
		$label = new GtkLabel('Adjust Brightness:');
		$label->set_alignment(0,0);
		$table->attach($label, 0, 4, 0, 1);
		$table->attach($scale, 0, 4, 1, 2);
		$vbox->pack_start($table, false, true);
		$this->show_properties($vbox, 'Brightness', 'apply_brightness', $scale);

	}
	/**
	 * Display the rotate properties
	 */
	public function rotate_properties()
	{
		$vbox = new GtkVBox();
		$vbuttonbox = new GtkVButtonBox();
		$clockwise = new GtkButton('90 Clockwise');
		$clockwise->connect_simple('clicked', array($this, 'apply_rotate'), 270);
		$cclockwise = new GtkButton('90 Counter-Clockwise');
		$cclockwise->connect_simple('clicked', array($this, 'apply_rotate'), 90);
		$flip = new GtkButton('Flip 180');
		$flip->connect_simple('clicked', array($this, 'apply_rotate'), 180);
		$vbuttonbox->add($clockwise);
		$vbuttonbox->add($cclockwise);
		$vbuttonbox->add($flip);
		$vbox->pack_start($vbuttonbox, false, true);
		$this->show_properties($vbox, 'Rotate', false);
	}
	/**
	 * Display the contrast filter properties
	 *
	 */
	public function contrast_properties()
	{
		$vbox = new GtkVBox();
		$table = new GtkTable(4, 4);
		$scale = new GtkHScale();
		$adjust = new GtkAdjustment(0, -10, 10, .5, 0, 0);
		$scale->set_adjustment($adjust);
		//$scale->connect('format-value', array($this, 'contrast_preview'));
		$label = new GtkLabel('Adjust Contrast:');
		$label->set_alignment(0,0);
		$table->attach($label, 0, 4, 0, 1);
		$table->attach($scale, 0, 4, 1, 2);
		$vbox->pack_start($table, false, true);
		$this->show_properties($vbox, 'Contrast', 'apply_contrast', $scale);

	}
	/**
	 * Display the smooth filter properties
	 *
	 */
	public function smooth_properties()
	{
		$vbox = new GtkVBox();
		$table = new GtkTable(4, 4);
		$scale = new GtkHScale();
		$adjust = new GtkAdjustment(0, 0, 10, .5, 0, 0);
		$scale->set_adjustment($adjust);
		$label = new GtkLabel('Adjust Smoothness:');
		$label->set_alignment(0,0);
		$table->attach($label, 0, 4, 0, 1);
		$table->attach($scale, 0, 4, 1, 2);
		$vbox->pack_start($table, false, true);
		$this->show_properties($vbox, 'Smooth', 'apply_smooth', $scale);

	}
	/**
	 * Display the resize option
	 *
	 */
	public function resize_properties()
	{
		$resize_vbox = new GtkVBox();
		$manip_table = new GtkTable(4, 4);
		$resize_label = new GtkLabel('Resize Image:');
		$resize_label->set_alignment(0,0);
		$percent_resize_check = new GtkCheckButton('Resize by percent');
		$constrain_check = new GtkCheckButton('Constrain');
		$new_height_label = new GtkLabel('Height:');
		$new_height_label->set_alignment(0, 0);
		$new_width_label = new GtkLabel('Width:');
		$new_width_label->set_alignment(0, 0);
		$new_height_entry = new GtkEntry();
		$new_height_entry->set_width_chars(4);
		$new_width_entry = new GtkEntry();
		$new_width_entry->set_width_chars(4);
		$constrain_check->connect('toggled', array($this, 'constrained_size'), $new_height_entry, $new_width_entry);
		$manip_table->attach($resize_label, 0, 4, 1, 2);
		$manip_table->attach($percent_resize_check, 0, 2, 2, 3);
		$manip_table->attach($constrain_check, 2, 4, 2, 3);
		$manip_table->attach($new_width_label, 0, 1, 3, 4);
		$manip_table->attach($new_height_label, 3, 4, 3, 4);
		$manip_table->attach($new_width_entry, 0, 1, 4, 5);
		$manip_table->attach($new_height_entry, 3, 4, 4, 5);
		$resize_vbox->pack_start($manip_table, false, true);
		$this->show_properties($resize_vbox, 'Resize Image', 'apply_resize', array($new_height_entry, $new_width_entry, $percent_resize_check));
	}
	/**
	 * Help user constrain size of dimensions
	 *
	 * @param GtkToggle $toggle
	 * @param GtkEntry $height
	 * @param GtkEntry $width
	 */
	public function constrained_size($toggle, GtkEntry $height, GtkEntry $width)
	{
		if($toggle->get_active())
		{
			$id = $width->connect('changed', array($this, 'update_height'), $height);
			$width->set_data('connectid', $id);
			$height->set_property('editable', false);
		}
		else
		{
			$height->set_property('editable', true);
			$width->disconnect($width->get_data('connectid'));
		}
	}
	/**
	 * Update the height field for contrains
	 *
	 * @param GtkEntry $width
	 * @param GtkEntry $height
	 */
	public function update_height(GtkEntry $width, GtkEntry $height)
	{
		$proportion = $this->orig_height/$this->orig_width;
		if($width->get_text() > 0)
		{
			$height->set_text(number_format($proportion * $width->get_text(), 0));
		}
	}
	/**
	 * Display the grayscale filter properties
	 *
	 */
	public function grayscale_properties()
	{
		$vbox = new GtkVBox();
		$label = new GtkLabel('Grayscale sucks all color from the photo, leaving shades of gray.');
		$label->set_alignment(0, 0);
		$label->set_line_wrap(true);
		$vbox->pack_start($label);
		$this->show_properties($vbox, 'Grayscale', 'apply_grayscale');
	}
	/**
	 * Display the negative filter properties
	 *
	 */
	public function negative_properties()
	{
		$vbox = new GtkVBox();
		$label = new GtkLabel('Negative inverses all the colors in the photo.');
		$label->set_alignment(0, 0);
		$label->set_line_wrap(true);
		$vbox->pack_start($label);
		$this->show_properties($vbox, 'Negative', 'apply_negative');
	}
	/**
	 * Display the gaussian blur filter properties
	 *
	 */
	public function gaussian_properties()
	{
		$vbox = new GtkVBox();
		$label = new GtkLabel('Applies a gaussian blur to the image.');
		$label->set_alignment(0, 0);
		$label->set_line_wrap(true);
		$vbox->pack_start($label);
		$this->show_properties($vbox, 'Gaussian Blur', 'apply_gaussian');
	}
	/**
	 * Display the emboss filter properties
	 *
	 */
	public function emboss_properties()
	{
		$vbox = new GtkVBox();
		$label = new GtkLabel('Embosses the image.');
		$label->set_alignment(0, 0);
		$label->set_line_wrap(true);
		$vbox->pack_start($label);
		$this->show_properties($vbox, 'Emboss', 'apply_emboss');
	}
	/**
	 * Display the sketch filter properties
	 *
	 */
	public function sketch_properties()
	{
		$vbox = new GtkVBox();
		$label = new GtkLabel('Makes the image look like a sketch.');
		$label->set_alignment(0, 0);
		$label->set_line_wrap(true);
		$vbox->pack_start($label);
		$this->show_properties($vbox, 'Sketch', 'apply_sketch');
	}

	/**
	 * shows a property box in the toolbar
	 *
	 * @param GtkVbox $widget
	 * @param string $label
	 * @param string $apply_method
	 * @param mixed $params
	 */
	public function show_properties(GtkVBox $widget, $label, $apply_method, $params=null)
	{
		$actions = CC_Actions::instance();
		$this->clear_properties();
		if($apply_method)
		{
			$property_buttons = new GtkHButtonBox();
			$property_buttons->set_layout(Gtk::BUTTONBOX_SPREAD);
			$apply_button = new GtkButton('Apply');
			$apply_button->connect_simple('clicked', array($this, $apply_method), $params);
			$property_buttons->add($apply_button);
			$property_buttons->add($actions->create_button_item('manip', 'cancel'));
			$widget->pack_start($property_buttons);
		}
		$this->properties_frame->add($widget);
		$this->properties_frame->set_label('Properties - ' . $label);
		$this->current_widget = $widget;
		$this->properties_frame->show_all();
	}
	/**
	 * clears the properties box
	 *
	 */
	public function clear_properties()
	{
		if($child = $this->properties_frame->get_child())
		{
			$this->properties_frame->remove($child);
		}
		$this->properties_frame->set_label('Properties');
		$this->current_widget = null;
	}
	/**
	 * sets the image
	 *
	 * @param GdkPixbuf $pixbuf
	 */
	public function set_pixbuf($pixbuf){
		$this->image->set_from_pixbuf($pixbuf);
	}
	/**
	 * update image information
	 *
	 * @param string $filename
	 * @param double $width
	 * @param double $height
	 */
	public function update_image_info($filename, $width, $height)
	{
		$string = '<b>Image: </b> '. basename($filename);
		$string .= ' <b>File Size: </b> ' . number_format((filesize($filename)/1000), 0) . 'KB';
		$string .= ' <b>Image Dimensions: </b>' . $width . ' X ' . $height;
		$this->image_info_label->set_markup($string);

	}
	/**
	 * preview the brighten filter
	 *
	 * @param GtkHScale $scale
	 * @param double $value
	 */
	public function brighten_preview(GtkScale $scale, $value)
	{
		$effect = new ImageEffects($this->orig_gd);
		$effect->brightness($value);
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * preview the contrast filter
	 *
	 * @param GtkHScale $scale
	 * @param double $value
	 */
	public function contrast_preview(GtkScale $scale, $value)
	{
		$pixbuf = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($pixbuf->get_gd());
		$effect->contrast($value);
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * undo operations
	 *
	 */
	public function undo()
	{
		if(count($this->edit_changes) != 0)
		{
			array_shift($this->edit_changes);
			$effect = ImageEffects::new_from_pixbuf($this->edit_pixbuf);
			$effect = new ImageEffects($effect->get_gd());
			foreach ($this->edit_changes as $add_effect) {
				if(is_array($add_effect))
				{
					$effect->$add_effect[0]($add_effect[1]);
				}
				else
				{
					$effect->$add_effect();
				}
			}
			$this->image->set_from_pixbuf($effect->get_pixbuf());
		}
	}
	public function on_help()
	{
	}

	/**
	 * apply the grayscale filter
	 *
	 */
	public function apply_grayscale()
	{
		array_unshift($this->edit_changes, 'grayscale');
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->grayscale();
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * apply the negative filter
	 *
	 */
	public function apply_negative()
	{
		array_unshift($this->edit_changes, 'negative');
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->negative();
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * apply the gaussian blur filter
	 *
	 */
	public function apply_gaussian()
	{
		array_unshift($this->edit_changes, 'gaussian_blur');
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->gaussian_blur();
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * apply the sketch filter
	 *
	 */
	public function apply_sketch()
	{
		array_unshift($this->edit_changes, 'sketch');
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->sketch();
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * apply the emboss filter
	 *
	 */
	public function apply_emboss()
	{
		array_unshift($this->edit_changes, 'emboss');
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->emboss();
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * apply the brightness filter
	 *
	 * @param GtkScale $scale
	 */
	public function apply_brightness(GtkScale $scale)
	{
		array_unshift($this->edit_changes, array('brightness', $scale->get_value()));
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->brightness($scale->get_value());
		$this->image->set_from_pixbuf($effect->get_pixbuf());

	}
	/**
	 * apply the contrast filter
	 *
	 * @param GtkScale $scale
	 */
	public function apply_contrast(GtkScale $scale)
	{
		array_unshift($this->edit_changes, array('contrast', $scale->get_value()));
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->contrast($scale->get_value());
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * apply the smooth filter
	 *
	 * @param GtkScale $scale
	 */
	public function apply_smooth(GtkScale $scale)
	{
		array_unshift($this->edit_changes, array('smooth', $scale->get_value()));
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect->smooth($scale->get_value());
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * rotate the iamge
	 *
	 * @param double $angle
	 */
	public function apply_rotate($angle)
	{
		array_unshift($this->edit_changes, array('rotate', $angle));
		$effect = ImageEffects::new_from_pixbuf($this->image->get_pixbuf());
		$effect = new ImageEffects($effect->get_gd());
		$effect = $effect->rotate($angle);
		$this->image->set_from_pixbuf($effect->get_pixbuf());
	}
	/**
	 * apply the resize
	 *
	 * @param mixed $params
	 */
	public function apply_resize($params)
	{
		$fail = false;
		$image = new ImageManipulation($this->orig_gd);
		$height = ltrim($params[0]->get_text());
		$width = ltrim($params[1]->get_text());
		if($params[2]->get_active())
		{
			if($height > 0 && $height != null && $width>0 && $width != null)
			{
				$image->resize_percent($width, $height);
			}
			else
			{
				$fail = true;
				$message = new CC_Message('Please make sure your values are larger than 0', 'Size To Small');
				$message->run();
				$message->destroy();
			}
		}
		else
		{
			if($height > 0 && $height != null && $width>0 && $width != null)
			{
				$image->resize($width, $height);
			}
			else
			{
				$fail = true;
				$message = new CC_Message('Please make sure your values are larger than 0.', 'Size To Small');
				$message->run();
				$message->destroy();

			}
		}
		if(!$fail)
		{
			$dialog = new CC_Message('Are you sure you want to resize this image?', 'Confirm Resize', CC_Message::QUESTION);
			switch ($dialog->run())
			{
				case Gtk::RESPONSE_YES:
					$dialog->destroy();
					$this->orig_gd = $image->get_gd();
					$pixbuf = new ImageEffects($this->orig_gd);
					if($pixbuf->get_width() > 800)
					{
						$pixbuf->get_thumb(800);
					}
					else if($width < 45)
					{
						$pixbuf->get_thumb(45);
					}
					$this->image->set_from_pixbuf($pixbuf->get_pixbuf());
					$this->save_changes(true);
					$this->update_image_info($this->file, $image->get_width(), $image->get_height());
					break;
				default: $dialog->destroy(); break;
			}
		}

	}
	/**
	 * save the changes of the filters
	 *
	 */
	public function save_changes($resize = null)
	{
		if(count($this->edit_changes) != 0 || $resize)
		{
			$image = new ImageEffects($this->orig_gd);
			foreach ($this->edit_changes as $effect) {
				if(is_array($effect))
				{
					$image->$effect[0]($effect[1]);
				}
				else
				{
					$image->$effect();
				}
			}
			if(!file_exists($this->file))
			{
				imagejpeg($image->get_gd(), $this->file, 100);
			}else
			{

				$data = @getimagesize($this->file);
				switch ($data[2]){
					case 1 : try {imagegif($image->get_gd(), $this->file);}
					catch(CC_Exception $e)
					{
						new CC_Message($e->getMessage(), 'Exception');
					}
					break;
					case 2 : try {imagejpeg($image->get_gd(), $this->file, 100);}
					catch(CC_Exception $e)
					{
						new CC_Message($e->getMessage(), 'Exception');
					}break;
					case 3 : try {imagepng($image->get_gd(), $this->file);}
					catch(CC_Exception $e)
					{
						new CC_Message($e->getMessage(), 'Exception');
					}break;
				}
			}
			$db = new CC_Db(CC::$dir . 'db' . DS . 'images.db');
			$db->update_checksum($this->orig_id);
			$this->edit_changes = array();
			$tmpthumb2 = $image->get_thumb(70);
			$db->put_thumbnail($this->orig_id,$tmpthumb2->get_gd());
			//Update the library thumbnail
			CC::$window->reload_library();
		}
	}

	public function on_return(){
		$this->destroy();
	}

}
?>