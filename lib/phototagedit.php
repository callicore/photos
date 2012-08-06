<?php

class CC_Phototagedit extends GtkDialog{

	protected $cat_combo;
	protected $tag_input;

	public function __construct(){
		$hbox = new gtkhbox();
		$vbox1 = new gtkvbox();
		$label1 = new Gtklabel('Catagorys :');
		$model = new GtkListStore(Gtk::TYPE_STRING);
		$this->cat_combo = new gtkcombobox();
		$this->cat_combo->set_model($model);
		$cellRenderer = new GtkCellRendererText();
		$this->cat_combo->pack_start($cellRenderer);
		$this->cat_combo->set_attributes($cellRenderer, 'text', 0);
		// $cats = db func
		foreach($cats as $cat) {
			$model->append(array($cat));
		}
		$vbox1->pack_start($label1);
		$vbox1->pack_start($this->cat_combo);
		$vbox2 = new gtkvbox();
		$label2 = new gtklabel("Tag :");
		$this->tag_input = new gtkentry();
		$vbox2->pack_start($label2);
		$vbox2->pack_start($this->tag_input);
		$vbox3 = new gtkvbox();
		$but_add = new GtkButton('Add tag');
		$but_cancel = new GtkButton('Cancel');
		$vbox3->pack_start($but_add);
		$vbox3->pack_start($but_cancel);
		$hbox->pack_start($vbox1);
		$hbox->pack_start($vbox2);
		$hbox->pack_start($vbox3);
		$this->add($hbox);
		$but_add->connect_simple('clicked',array($this,'on_add'));
		$but_cancel->connect_simple('clicked',array($this,'on_quit'));
		$this->connect_simple('destroy',array($this,'on_quit'));
	}

	public function on_add(){
		$model = $this->cat_combo->get_model();
    	$cat = $model->get_value($combobox->get_active_iter(), 0);
    	$tag = $this->tag_input->get_text();
    	// add tag db code
    	CC::$window->tagscroll->remove(CC::$window->taglist);
    	CC::$window->taglist = new CC_Taglist(CC::$window->db);
    	CC::$window->tagscroll->add(CC::$window->taglist);
    	CC::$window->tagscroll->show_all();
	}
	
	public function on_quit(){
		$this->destroy();
	}

}

?>