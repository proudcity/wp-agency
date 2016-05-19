<?php
/*
Plugin Name: Proud Agency
Plugin URI: http://proudcity.com/
Description: Declares an Agency custom post type.
Version: 1.0
Author: ProudCity
Author URI: http://proudcity.com/
License: Affero GPL v3
*/
// @todo: use CMB2: https://github.com/WebDevStudios/CMB2 or https://github.com/humanmade/Custom-Meta-Boxes
namespace Proud\Agency;

// Load Extendible
// -----------------------
if ( ! class_exists( 'ProudPlugin' ) ) {
  require_once( plugin_dir_path(__FILE__) . '../wp-proud-core/proud-plugin.class.php' );
}

// We need the pagebuilder file for the default pagebuilder layout
// @todo: Make this WORK!!
// @todo: dont make this required, gracefully degrade
//require_once( plugin_dir_path(__FILE__) . '../wp-proud-core/modules/so-pagebuilder/proud-so-pagebuilder.php' );

class Agency extends \ProudPlugin {

  static $key = 'agency_edit';

  public function __construct() {
    parent::__construct( array(
      'textdomain'     => 'wp-proud-agency',
      'plugin_path'    => __FILE__,
    ) );
    $this->hook( 'init', 'create_agency' );
    $this->hook( 'admin_init', 'agency_admin' );
    $this->hook( 'admin_enqueue_scripts', 'agency_assets' );
    $this->hook( 'plugins_loaded', 'agency_init_widgets' );
    $this->hook( 'save_post', 'add_agency_section_fields', 10, 2 );
    $this->hook( 'rest_api_init', 'agency_rest_support' );
    $this->hook( 'before_delete_post', 'delete_agency_menu' );
  }

  //add assets
  function agency_assets() {
    $path = plugins_url('assets/',__FILE__);
    wp_enqueue_script('proud-agency/js', $path . 'js/proud-agency.js', ['proud','jquery'], null, true);
  }

  // Init on plugins loaded
  function agency_init_widgets() {
    require_once plugin_dir_path(__FILE__) . '/widgets/agency-contact-widget.class.php';
    require_once plugin_dir_path(__FILE__) . '/widgets/agency-hours-widget.class.php';
    require_once plugin_dir_path(__FILE__) . '/widgets/agency-social-links-widget.class.php';
    require_once plugin_dir_path(__FILE__) . '/widgets/agency-menu-widget.class.php';
  }

  public function create_agency() {
      $labels = array(
          'name'               => _x( 'Agencies', 'post name', 'wp-agency' ),
          'singular_name'      => _x( 'Agency', 'post type singular name', 'wp-agency' ),
          'menu_name'          => _x( 'Agencies', 'admin menu', 'wp-agency' ),
          'name_admin_bar'     => _x( 'Agency', 'add new on admin bar', 'wp-agency' ),
          'add_new'            => _x( 'Add New', 'agency', 'wp-agency' ),
          'add_new_item'       => __( 'Add New Agency', 'wp-agency' ),
          'new_item'           => __( 'New Agency', 'wp-agency' ),
          'edit_item'          => __( 'Edit Agency', 'wp-agency' ),
          'view_item'          => __( 'View Agency', 'wp-agency' ),
          'all_items'          => __( 'All agencies', 'wp-agency' ),
          'search_items'       => __( 'Search agency', 'wp-agency' ),
          'parent_item_colon'  => __( 'Parent agency:', 'wp-agency' ),
          'not_found'          => __( 'No agencies found.', 'wp-agency' ),
          'not_found_in_trash' => __( 'No agencies found in Trash.', 'wp-agency' )
      );

      $args = array(
          'labels'             => $labels,
          'description'        => __( 'Description.', 'wp-agency' ),
          'public'             => true,
          'publicly_queryable' => true,
          'show_ui'            => true,
          'show_in_menu'       => true,
          'query_var'          => true,
          'rewrite'            => array( 'slug' => _x( 'agencies', 'slug', 'wp-agency' ) ),
          'capability_type'    => 'post',
          'has_archive'        => false,
          'hierarchical'       => false,
          'menu_position'      => null,
          'show_in_rest'       => true,
          'rest_base'          => 'agencies',
          'rest_controller_class' => 'WP_REST_Posts_Controller',
          'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt')
      );

      register_post_type( 'agency', $args );
  }

  public function agency_admin() {
    add_meta_box( 'agency_section_meta_box',
      'Agency type',
      array($this, 'display_agency_section_meta_box'),
      'agency', 'normal', 'high'
    );
    
    // @todo: see if we can move the editor below the fields (at least agency type?)
    // See: https://wordpress.org/support/topic/move-custom-meta-box-above-editor
  }

  public function agency_rest_support() {
    register_api_field( 'agency',
          'meta',
          array(
              'get_callback'    => 'agency_rest_metadata',
              'update_callback' => null,
              'schema'          => null,
          )
      );
  }

  /**
   * Alter the REST endpoint.
   * Add metadata to the post response
   */
  public function agency_rest_metadata( $object, $field_name, $request ) {
      $return = array('social' => array());
      foreach ($this->agency_social_services() as $key => $label) {
        if ($value = get_post_meta( $object[ 'id' ], 'social_'.$key, true )) {
          $return['social'][$key] = $value;
        }
      }
      foreach ($this->agency_contact_fields() as $key => $label) {
        if ($value = get_post_meta( $object[ 'id' ], $key, true )) {
          $return[$key] = $value;
        }
      }
      return $return;
  }


  public function build_fields($id) {
    $this->fields = [];

    $type = get_post_meta( $id, 'agency_type', true );
    $type = $type ? $type : 'page';
    $this->fields['agency_type'] = [
      '#type' => 'radios',
      '#title' => __('Type'),
      //'#description' => __('The type of search to fallback on when users don\'t find what they\'re looking for in the autosuggest search and make a full site search.', 'proud-settings'),
      '#name' => 'agency_type',
      '#options' => array(
        'page' => __('Single page', 'proud'),
        'external' => __('External link', 'proud'),
        'section' => __('Section', 'proud'),
      ),
      '#value' => $type,
    ];

    $this->fields['agency_url'] = [
      '#type' => 'text',
      '#title' => __('URL'),
      '#description' => __('Enter the full URL to an existing site'),
      '#name' => 'agency_url',
      '#value' => esc_url( get_post_meta( $id, 'url', true ) ),
      '#states' => [
        'visible' => [
          'agency_type' => [
            'operator' => '==',
            'value' => ['external'],
            'glue' => '||'
          ],
        ],
      ],
    ];

    $menus = get_registered_nav_menus();
    $menus = get_terms( 'nav_menu', array( 'hide_empty' => false ) );
    global $menuArray;
    $menuArray = array(
      '' => 'No menu',
      'new' => 'Create new menu',
    );
    foreach ( $menus as $menu ) {
      $menuArray[$menu->slug] = $menu->name;
    }
    $menu = get_post_meta( $id, 'post_menu', true );
    $menu = $menu ? $menu : 'new';
    $isNew = empty($agency->post_title) ? 1 : 0;

    $this->fields['post_menu'] = [
      '#type' => 'select',
      '#title' => __('Menu'),
      //'#description' => __('Enter the full url to the payment page'),
      '#name' => 'post_menu',
      '#options' => $menuArray,
      '#value' => $menu,
      '#states' => [
        'visible' => [
          'agency_type' => [
            'operator' => '==',
            'value' => ['section'],
            'glue' => '||'
          ],
        ],
      ],
    ];

    $this->fields['agency_icon'] = [
      '#type' => 'fa-icon',
      '#title' => __('Icon'),
      '#description' => __('If you are using the Icon Button list style, select an icon'),
      '#name' => 'agency_icon',
      '#value' => get_post_meta( $id, 'agency_icon', true ),
    ];

    return $this->fields;
  }

  /**
   * Displays the Agency Type metadata fieldset.
   */
  public function display_agency_section_meta_box( $agency ) {
    $this->build_fields($agency->ID);
    $form = new \Proud\Core\FormHelper( self::$key, $this->fields );
    $form->printFields(); 

    // Add js settings
    global $proudcore;
    $proudcore->addJsSettings([
      'proud_agency' => [
        'isNewPost' => empty($agency->post_title),
        'agency_panels' => [
          'section' => $this->agency_pagebuilder_code('section'),
          'page' => $this->agency_pagebuilder_code('page') // @TODO change to page + figure out how to update on click 
        ]
      ]
    ]);
  }


  /**
   * Saves contact metadata fields 
   */
  /*public function add_payment_fields( $id, $payment ) {
    if ( $payment->post_type == 'payment' ) {
      foreach ($this->build_fields($id) as $key => $field) {
        if ( !empty( $_POST[$key] ) ) {  // @todo: check if it has been set already to allow clearing of value
          update_post_meta( $id, $key, $_POST[$key] );
        }
      }
    }
  }*/


  /**
   * Saves social metadata fields and saves/creates the menu
   */
  public function add_agency_section_fields( $id, $agency ) {
    if ( $agency->post_type == 'agency' && !empty( $_POST['agency_type'] ) ) {
      $type = $_POST['agency_type'];
      update_post_meta( $id, 'agency_type', $type );
      if ('external' === $type) {
        $url = $_POST['agency_url'];
        if ( empty($url) ) {
          delete_post_meta( $id, 'url');
        }
        else {
          update_post_meta( $id, 'url', esc_url( $url ));
        }
      }
      else if ('section' === $type) {
        $menu = $_POST['post_menu'];
        if ('new' === $menu) {
          $menuId = wp_create_nav_menu($agency->post_title);
          $objMenu = get_term_by( 'id', $menuId, 'nav_menu');
          $menu = $objMenu->slug;
        }
        if (!is_array($menu)) {
          update_post_meta( $id, 'post_menu', $menu );
        }
      }

      update_post_meta( $id, 'agency_icon', $_POST['agency_icon']);
    }
  }




   /**
   * Delete menu when agency is deleted.
   */
  public function delete_agency_menu( $post_id ) {
    $menu = get_post_meta( $post_id, 'post_menu' );
    wp_delete_nav_menu( $menu );
  }

  // @todo: get this from proud-so-pagebuilder.php (proud-core)
  private function agency_pagebuilder_code($type) {
    if($type === 'section') {
      $code = array(
        'name' => __('Agency home page', 'proud'),    
        'description' => __('Agency header and sidebar with contact info', 'proud'),    // Optional
        'widgets' => 
        array (
          0 => 
          array (
            'text' => '<h1>[title]</h1>',
            'headertype' => 'header',
            'background' => 'image',
            'pattern' => '',
            'repeat' => 'full',
            'image' => '[featured-image]',
            'make_inverse' => 'make_inverse',
            'panels_info' => 
            array (
              'class' => 'JumbotronHeader',
              'grid' => 0,
              'cell' => 0,
              'id' => 0,
            ),
          ),
          1 => 
          array (
            'title' => '',
            'panels_info' => 
            array (
              'class' => 'AgencyMenu',
              'raw' => false,
              'grid' => 1,
              'cell' => 0,
              'id' => 1,
            ),
          ),
          2 => 
          array (
            'title' => 'Connect',
            'panels_info' => 
            array (
              'class' => 'AgencySocial',
              'raw' => false,
              'grid' => 1,
              'cell' => 0,
              'id' => 2,
            ),
          ),
          3 => 
          array (
            'title' => 'Contact',
            'panels_info' => 
            array (
              'class' => 'AgencyContact',
              'raw' => false,
              'grid' => 1,
              'cell' => 0,
              'id' => 3,
            ),
          ),
          4 => 
          array (
            'title' => 'Hours',
            'panels_info' => 
            array (
              'class' => 'AgencyHours',
              'raw' => false,
              'grid' => 1,
              'cell' => 0,
              'id' => 4,
            ),
          ),
          5 => 
            array (
              'title' => '',
              'text' => '',
              'text_selected_editor' => 'tinymce',
              'autop' => true,
              '_sow_form_id' => '56ab38067a600',
              'panels_info' => 
              array (
                'class' => 'SiteOrigin_Widget_Editor_Widget',
                'grid' => 1,
                'cell' => 1,
                'id' => 5,
                'style' => 
                array (
                  'background_image_attachment' => false,
                  'background_display' => 'tile',
                ),
              ),
            ),
        ),
        'grids' => 
        array (
          0 => 
          array (
            'cells' => 1,
            'style' => 
            array (
              'row_stretch' => 'full',
              'background_display' => 'tile',
            ),
          ),
          1 => 
          array (
            'cells' => 2,
            'style' => 
            array (
            ),
          ),
        ),
        'grid_cells' => 
        array (
          0 => 
          array (
            'grid' => 0,
            'weight' => 1,
          ),
          1 => 
          array (
            'grid' => 1,
            'weight' => 0.33345145287029998,
          ),
          2 => 
          array (
            'grid' => 1,
            'weight' => 0.66654854712970002,
          ),
        ),
      );
    }
    else {
      $code = array(
        'name' => __('Agency home page', 'proud'),    
        'description' => __('Agency header and sidebar with contact info', 'proud'),    // Optional
        'widgets' => 
        array (
          0 => 
          array (
            'text' => '<h1>[title]</h1>',
            'headertype' => 'header',
            'background' => 'image',
            'pattern' => '',
            'repeat' => 'full',
            'image' => '[featured-image]',
            'make_inverse' => 'make_inverse',
            'panels_info' => 
            array (
              'class' => 'JumbotronHeader',
              'grid' => 0,
              'cell' => 0,
              'id' => 0,
            ),
          ),
          1 => 
          array (
            'title' => 'Connect',
            'panels_info' => 
            array (
              'class' => 'AgencySocial',
              'raw' => false,
              'grid' => 1,
              'cell' => 0,
              'id' => 2,
            ),
          ),
          2 => 
          array (
            'title' => 'Contact',
            'panels_info' => 
            array (
              'class' => 'AgencyContact',
              'raw' => false,
              'grid' => 1,
              'cell' => 0,
              'id' => 3,
            ),
          ),
          3 => 
          array (
            'title' => 'Hours',
            'panels_info' => 
            array (
              'class' => 'AgencyHours',
              'raw' => false,
              'grid' => 1,
              'cell' => 0,
              'id' => 4,
            ),
          ),
          4 => 
            array (
              'title' => '',
              'text' => '',
              'text_selected_editor' => 'tinymce',
              'autop' => true,
              '_sow_form_id' => '56ab38067a600',
              'panels_info' => 
              array (
                'class' => 'SiteOrigin_Widget_Editor_Widget',
                'grid' => 1,
                'cell' => 1,
                'id' => 5,
                'style' => 
                array (
                  'background_image_attachment' => false,
                  'background_display' => 'tile',
                ),
              ),
            ),
        ),
        'grids' => 
        array (
          0 => 
          array (
            'cells' => 1,
            'style' => 
            array (
              'row_stretch' => 'full',
              'background_display' => 'tile',
            ),
          ),
          1 => 
          array (
            'cells' => 2,
            'style' => 
            array (
            ),
          ),
        ),
        'grid_cells' => 
        array (
          0 => 
          array (
            'grid' => 0,
            'weight' => 1,
          ),
          1 => 
          array (
            'grid' => 1,
            'weight' => 0.33345145287029998,
          ),
          2 => 
          array (
            'grid' => 1,
            'weight' => 0.66654854712970002,
          ),
        ),
      );
    }
    return json_encode($code);
  }


} // class
$Agency = new Agency;




/**
 * Add the Social networks metabox
 */
class AgencyContact extends \ProudPlugin {

  static $key = 'agency_contact';

  public function __construct() {
    parent::__construct( array(
      'textdomain'     => 'wp-proud-agency',
      'plugin_path'    => __FILE__,
    ) );
   
    $this->hook( 'save_post', 'add_agency_contact_fields', 10, 2 );
    $this->hook( 'admin_init', 'agency_contact_admin' );

  }

  public function agency_contact_admin() {
    add_meta_box( 'agency_contact_meta_box',
      'Contact information',
      array($this, 'display_agency_contact_meta_box'),
      'agency', 'normal', 'high'
    );
  }

  public function build_fields($id) {
    $this->fields = [];

    $this->fields['name'] = [
      '#type' => 'text',
      '#title' => __( 'Contact name' ),
      '#name' => 'name',
      '#value' => esc_html( get_post_meta( $id, 'name', true ) ),
    ];

    $this->fields['email'] = [
      '#type' => 'text',
      '#title' => __( 'Contact email' ),
      '#name' => 'email',
      '#value' => esc_html( get_post_meta( $id, 'email', true ) ),
    ];

    $this->fields['phone'] = [
      '#type' => 'text',
      '#title' => __( 'Contact phone' ),
      '#name' => 'phone',
      '#value' => esc_html( get_post_meta( $id, 'phone', true ) ),
    ];

    $this->fields['address'] = [
      '#type' => 'textarea',
      '#title' => __( 'Contact address' ),
      '#name' => 'address',
      '#value' => esc_html( get_post_meta( $id, 'address', true ) ),
    ];

    $this->fields['hours'] = [
      '#type' => 'textarea',
      '#title' => __( 'Contact hours' ),
      '#name' => 'hours',
      '#description' => __( 'Example:<Br/>Sunday: Closed<Br/>Monday: 9:30am - 9:00pm<Br/>Tuesday: 9:00am - 5:00pm' ),
      '#value' => esc_html( get_post_meta( $id, 'hours', true ) ),
    ];

    return $this->fields;
  }

  public function display_agency_contact_meta_box( $agency ) {
    $this->build_fields($agency->ID);
    $form = new \Proud\Core\FormHelper( self::$key, $this->fields );
    $form->printFields();
  }

  /**
   * Saves contact metadata fields 
   */
  public function add_agency_contact_fields( $id, $agency ) {
    if ( $agency->post_type == 'agency' ) {
      foreach ($this->build_fields($id) as $key => $field) {
        
        if ( !empty( $_POST[$key] ) ) {  // @todo: check if it has been set already to allow clearing of value
          update_post_meta( $id, $key, $_POST[$key] );
        }
      }
    }
  }

}
new AgencyContact;



/**
 * Add the Social networks metabox
 */
class AgencySocial extends \ProudPlugin {
  
  static $key = 'agency_social';

  public function __construct() {
    parent::__construct( array(
      'textdomain'     => 'wp-proud-agency',
      'plugin_path'    => __FILE__,
    ) );
   
    $this->hook( 'save_post', 'add_agency_social_fields', 10, 2 );
    $this->hook( 'admin_init', 'agency_social_admin' );

  }

  public function agency_social_admin() {
    add_meta_box( 'agency_social_meta_box',
      'Social Media Accounts',
      array($this, 'display_agency_social_meta_box'),
      'agency', 'normal', 'high'
    );
  }

  
  public function agency_social_services() {
    return array(
      'facebook' => 'http://facebook.com/pages/',
      'twitter' => 'http://twitter.com/',
      'instagram' => 'http://instagram.com/',
      'youtube' => 'http://youtube.com/',
      'rss' => 'Enter url to RSS news feed',
      'ical' => 'Enter url to iCal calendar feed',
    );
  }

  public function build_fields($id) {
    $this->fields = [];

    foreach ($this->agency_social_services() as $service => $label) {
      $this->fields['social_' . $service] = [
        '#type' => 'text',
        '#title' => __( ucfirst($service) ),
        '#name' => 'social_' . $service,
        '#value' => esc_html( get_post_meta( $id, 'social_' . $service, true ) ),
      ];
    }

    return $this->fields;
  }

  public function display_agency_social_meta_box( $agency ) {
    $this->build_fields($agency->ID);
    $form = new \Proud\Core\FormHelper( self::$key, $this->fields );
    $form->printFields();
  }

  /**
   * Saves contact metadata fields 
   */
  public function add_agency_social_fields( $id, $agency ) {
    if ( $agency->post_type == 'agency' ) {
      foreach ($this->build_fields($id) as $key => $field) {
        if ( !empty( $_POST[$key] ) ) {  // @todo: check if it has been set already to allow clearing of value
          update_post_meta( $id, $key, $_POST[$key] );
        }
      }
    }
  }
  // @todo: do we want smarter saving logic?
  /*public function add_agency_social_fields( $id, $agency ) {
    if ( $agency->post_type == 'agency' ) {
      foreach ($this->agency_social_services() as $service => $label) {
        $field = 'social_'.$service;
        $old = get_post_meta( $id, $field, true );
        $new = !empty( $_POST['agency_social_' . $service] ) ? $_POST['agency_social_' . $service] : null;
        if( !is_null( $old ) ){
          if ( is_null( $new ) ){
            delete_post_meta( $id, $field );
          } else {
            update_post_meta( $id, $field, $new, $old );
          }
        } elseif ( !is_null( $new ) ){
          add_post_meta( $id, $field, $new, true );
        }
      }
    }
  }*/

}
new AgencySocial;


/**
 * Gets the url for the agency homepage (internal or external)
 */
function get_agency_permalink($post = 0) {
  $post = $post > 0 ? $post : get_the_ID();
  $url = get_post_meta( $post, 'url', true );

  if ( get_post_meta( $post, 'agency_type', true ) === 'external' && !empty($url) ) {
    return esc_html( $url );
  }
  else {
    return esc_url( apply_filters( 'the_permalink', get_permalink( $post ), $post ) );
  }
}

/**
 * Returns the list of social fields (also sued in agency-social-links-widget.php)
 */
function agency_social_services() {
  return array(
    'facebook' => 'http://facebook.com/pages/',
    'twitter' => 'http://twitter.com/',
    'instagram' => 'http://instagram.com/',
    'youtube' => 'http://youtube.com/',
    'rss' => 'Enter url to RSS news feed',
    'ical' => 'Enter url to iCal calendar feed',
  );
}