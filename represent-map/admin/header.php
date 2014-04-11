<?php
include "../include/db.php";
// get task
if(isset($_GET['task'])) { $task = $_GET['task']; } 
else if(isset($_POST['task'])) { $task = $_POST['task']; }

// get view
if(isset($_GET['view'])) { $view = $_GET['view']; } 
else if(isset($_POST['view'])) { $view = $_POST['view']; }
else { $view = ""; }

// get page
if(isset($_GET['p'])) { $p = $_GET['p']; } 
else if(isset($_POST['p'])) { $p = $_POST['p']; }
else { $p = 1; }

// get search
if(isset($_GET['search'])) { $search = $_GET['search']; } 
else if(isset($_POST['search'])) { $search = $_POST['search']; }
else { $search = ""; }

// make sure admin is logged in
if($page != "login") {
  if($_COOKIE["representmap_user"] != crypt($admin_user, $admin_user) OR $_COOKIE["representmap_pass"] != crypt($admin_pass, $admin_pass)) {
    header("Location: login.php");
    exit;
  }
}

// connect to db
mysql_connect($db_host, $db_user, $db_pass) or die(mysql_error());
mysql_select_db($db_name) or die(mysql_error());

// get marker totals
$total_approved = mysql_num_rows(mysql_query("SELECT id FROM places WHERE approved='1'"));
$total_rejected = mysql_num_rows(mysql_query("SELECT id FROM places WHERE approved='0'"));
$total_pending = mysql_num_rows(mysql_query("SELECT id FROM places WHERE approved IS null"));
$total_all = mysql_num_rows(mysql_query("SELECT id FROM places"));

// admin header
$admin_head = "
  <html>
  <head>
    <title>RepresentMap Admin</title>
    <link href='../bootstrap/css/bootstrap.css' rel='stylesheet' type='text/css' />
    <link href='../bootstrap/css/bootstrap-responsive.css' rel='stylesheet' type='text/css' />
    <link rel='stylesheet' href='admin.css' type='text/css' />
    <script src='../scripts/jquery-1.7.1.js' type='text/javascript' charset='utf-8'></script>
    <script src='../bootstrap/js/bootstrap.js' type='text/javascript' charset='utf-8'></script>
    <script src='https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false' type='text/javascript' charset='utf-8'></script>
  </head>
  <body>
";
if($page != "login") {
  $admin_head .= "
    <div class='navbar navbar-fixed-top'>
      <div class='navbar-inner'>
        <div class='container'>
          <a class='brand' href='index.php'>
            RepresentMap
          </a>
          <ul class='nav'>
            <li>
              <a href='#modal_add' class='btn btn-large btn-success' data-toggle='modal'><i class='icon-plus-sign icon-white'></i>Add Something</a>
            </li>
            <li"; if($view == "") { $admin_head .= " class='active'"; } $admin_head .= ">
              <a href='index.php'>All Listings</a>
            </li>
            <li"; if($view == "approved") { $admin_head .= " class='active'"; } $admin_head .= ">
              <a href='index.php?view=approved'>
                Approved
                <span class='badge badge-info'>$total_approved</span>
              </a>
            </li>
            <li"; if($view == "pending") { $admin_head .= " class='active'"; } $admin_head .= ">
              <a href='index.php?view=pending'>
                Pending
                <span class='badge badge-info'>$total_pending</span>
              </a>
            </li>
            <li"; if($view == "rejected") { $admin_head .= " class='active'"; } $admin_head .= ">
              <a href='index.php?view=rejected'>
                Rejected
                <span class='badge badge-info'>$total_rejected</span>
              </a>
            </li>
          </ul>
          <form class='navbar-search pull-left' action='index.php' method='get'>
            <input type='text' name='search' class='search-query' placeholder='Search' autocomplete='off' value='$search'>
          </form>
          <ul class='nav pull-right'>
            <li><a href='login.php?task=logout'>Sign Out</a></li>
          </ul>
        </div>
      </div>
    </div>

        <!-- add something modal -->
    <div class='modal hide' id='modal_add'>
      <form action='../add.php' id='modal_addform' class='form-horizontal'>
        <div class='modal-header'>
          <button type='button' class='close' data-dismiss='modal'>×</button>
          <h3>Add something!</h3>
        </div>
        <div class='modal-body'>
          <div id='result'></div>
          <fieldset>
            <div class='control-group'>
              <label class='control-label' for='add_owner_name'>Your Name</label>
              <div class='controls'>
                <input type='text' class='input-xlarge' name='owner_name' id='add_owner_name' maxlength='100'>
              </div>
            </div>
            <div class='control-group'>
              <label class='control-label' for='add_owner_email'>Your Email</label>
              <div class='controls'>
                <input type='text' class='input-xlarge' name='owner_email' id='add_owner_email' maxlength='100'>
              </div>
            </div>
            <div class='control-group'>
              <label class='control-label' for='add_title'>Company Name</label>
              <div class='controls'>
                <input type='text' class='input-xlarge' name='title' id='add_title' maxlength='100' autocomplete='off'>
              </div>
            </div>
            <div class='control-group'>
              <label class='control-label' for='input01'>Company Type</label>
              <div class='controls'>
                <select name='type' id='add_type' class='input-xlarge'>
                  <option value='startup'>Startup</option>
                  <option value='incubator'>Incubator</option>
                </select>
              </div>
            </div>
            <div class='control-group'>
              <label class='control-label' for='add_address'>Address</label>
              <div class='controls'>
                <input type='text' class='input-xlarge' name='address' id='add_address'>
                <p class='help-block'>
                  Should be your <b>full street address (including city and zip)</b>.
                  If it works on Google Maps, it will work here.
                </p>
              </div>
            </div>
            <div class='control-group'>
              <label class='control-label' for='add_uri'>Website URL</label>
              <div class='controls'>
                <input type='text' class='input-xlarge' id='add_uri' name='uri' placeholder='http://''>
                <p class='help-block'>
                  Should be your full URL with no trailing slash, e.g. 'http://www.yoursite.com'
                </p>
              </div>
            </div>
            <div class='control-group'>
              <label class='control-label' for='add_description'>Description</label>
              <div class='controls'>
                <input type='text' class='input-xlarge' id='add_description' name='description' maxlength='150'>
                <p class='help-block'>
                  Brief, concise description. What's your product? What problem do you solve? Max 150 chars.
                </p>
              </div>
            </div>
          </fieldset>
        </div>
        <div class='modal-footer'>
          <button type='submit' class='btn btn-primary'>Submit for Review</button>
          <a href='#' class='btn' data-dismiss='modal' style='float: right;'>Close</a>
        </div>
      </form>
    </div>
    <script>
      // add modal form submit
      $('#modal_addform').submit(function(event) {
        event.preventDefault();
        // get values
        var $form = $( this ),
            owner_name = $form.find( '#add_owner_name' ).val(),
            owner_email = $form.find( '#add_owner_email' ).val(),
            title = $form.find( '#add_title' ).val(),
            type = $form.find( '#add_type' ).val(),
            address = $form.find( '#add_address' ).val(),
            uri = $form.find( '#add_uri' ).val(),
            description = $form.find( '#add_description' ).val(),
            url = $form.attr( 'action' );

        // send data and get results
        $.post( url, { owner_name: owner_name, owner_email: owner_email, title: title, type: type, address: address, uri: uri, description: description },
          function( data ) {
            var content = $( data ).find( '#content' );

            // if submission was successful, show info alert
            if(data == 'success') {
              $('#modal_addform #result').html('We have received your submission and will review it shortly. Thanks!');
              $('#modal_addform #result').addClass('alert alert-info');
              $('#modal_addform p').css('display', 'none');
              $('#modal_addform fieldset').css('display', 'none');
              $('#modal_addform .btn-primary').css('display', 'none');

            // if submission failed, show error
            } else {
              $('#modal_addform #result').html(data);
              $('#modal_addform #result').addClass('alert alert-danger');
            }
          }
        );
      });
    </script>
  ";
}
$admin_head .= "
  <div id='content'>
";




// if startup genome enabled, show message here
if($sg_enabled) {
  $admin_head .= "
    <div class='alert alert-info'>
      Note: You have Startup Genome integration enabled in your config file (/include/db.php).
      If you want to make changes to the markers on your map, please do so from the 
      <a href='http://www.startupgenome.com'>Startup Genome website</a>. Any changes
      you make here may not persist on your map unless you turn off Startup Genome mode.
    </div>
  ";  
}




// admin footer 
$admin_foot = "
    </div>
  </body>
</html>
";




?>