<?php defined("SYSPATH") or die("No direct script access."); ?>
<?php
/*
************************************************************************
*  Name      - resource_Controller
*  Function  - handles retreiving a resource from the protected 
*                data store.  No direct access to resources allowed.
*  Notes     - for use with Gallery - a web based photo album viewer and editor
*
*  Copyright - (c) 2014, Kast Solutions.  All Rights Reserved.
*
*  Modifications
*  Date      Who                    Description
*  10-14-14  Keith Kastor           Initial Release
************************************************************************
*/
class Resource_Controller extends Controller {

  public function index() {
    if (!identity::active_user()->admin) {
      url::redirect(item::root()->abs_url());
    }

    $v = new View("welcome_message.html");
    $v->user = identity::active_user();
    print $v;
  } // function index()


  public function 

} // class Resource_Controller
