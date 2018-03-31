<?php
// Composer deps
require_once('vendor/autoload.php');

// Fat-free
$f3 = Base::instance();

// Env vars
require('config.php');

// Fat-free vars
$f3->set('TEMP', $f3->get('AWM_GCLOUD_BUCKET'));
$f3->set('DEBUG', 0); // 0 = production; 3 = debug mode
$f3->set('CACHE', 'memcached=localhost:11211');
$f3->set('TZ','America/Bahia');
$f3->set('LOCALES','dict/');
$f3->set('LANGUAGE', $_COOKIE['language']);
$f3->set('FALLBACK','en-US');

// ======================
// Google Datastore
// ======================

use Google\Cloud\Datastore\DatastoreClient;

$f3->set('datastore', new DatastoreClient([
    'projectId' => $f3->get('AWM_DATASTORE_PROJECTID')
]));
   
// ======================
// App
// ======================

$f3->route('GET /',
  function($f3) {
    $f3->set('page','home');
  
    $query = $f3->get('datastore')->query()
      ->kind('institutions')
      ->order('name');
    
    $res = $f3->get('datastore')->runQuery($query);
    
    $a = 0;
    foreach ($res as $locais){
	    $pinos .= 'var marker' . 
	    $a . ' = L.marker([' . 
        $locais->lat . ',' . 
        $locais->long . ']).addTo(mymap)'.
        '.bindPopup(\'' . addslashes($locais->name) . 
        '<br><a href=\"./info/' . $locais->key()->pathEndIdentifier() . '\">info</a>' . '\');' . 
        PHP_EOL;
        
        $a++;
	  }
    
	  $f3->set('pinagem', $pinos);
    
    echo \Template::instance()->render('templates/home.html');
  }
);

$f3->route('GET /add',
  function($f3) {
    $f3->set('page','add');
 
    echo \Template::instance()->render('templates/home.html');
  }
);

$f3->route('POST /proc-add',
  function($f3) {
    $f3->set('page','proc-add');
   
    $institution = $f3->get('datastore')->entity('institutions', [
      'name' => $f3->get('POST.name'),  
      'identifier' => $f3->get('POST.identifier'),
      'address' => $f3->get('POST.address'),
      'city' => $f3->get('POST.city'),
      'district' => $f3->get('POST.district'),
      'country' => $f3->get('POST.country'),
      'url' => $f3->get('POST.url'),
      'email' => $f3->get('POST.email'),
      'lat' => $f3->get('POST.lat'),
      'long' => $f3->get('POST.long'),
      'collaborator-name' => $f3->get('POST.collaborator-name'),
      'collaborator-email' => $f3->get('POST.collaborator-email')
    ]);
    
    $f3->get('datastore')->insert($institution);
    
    echo \Template::instance()->render('templates/home.html');
  }
);

$f3->route('GET /stats',
  function($f3) {
    $f3->set('page','stats');

    // 
    
    
    echo \Template::instance()->render('templates/home.html');
  }
);

$f3->route('GET /about',
  function($f3) {
    $f3->set('page','about');

    echo \Template::instance()->render('templates/home.html');
  }
);

$f3->route('GET /info/@id',
   function($f3) {
      $f3->set('page','info');
	  
	  	  
	  echo \Template::instance()->render('etc/templates/default.html');
   }
);

$f3->route('GET /login',
  function($f3) {
    $f3->set('page','login');

    echo \Template::instance()->render('templates/login.html');
  }
);

$f3->run();
