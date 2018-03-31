<?php
// Composer deps
require_once('vendor/autoload.php');

// Fat-free
$f3 = Base::instance();

// ======================
// Language selector
// ======================

if($f3->get('GET.language')){
  setcookie('language', $f3->get('GET.language'));
  header('Location: '.$_SERVER['HTTP_REFERER']);
}

// Env vars
require('config.php');

// Fat-free vars
$f3->set('TEMP', $f3->get('AWM_GCLOUD_BUCKET'));
$f3->set('DEBUG', 3); // 0 = production; 3 = debug mode
$f3->set('CACHE', 'memcached=localhost:11211');
$f3->set('TZ','America/Bahia');
$f3->set('LOCALES','./dict/');
$f3->set('LANGUAGE', $_COOKIE['language']);
$f3->set('FALLBACK','en-US');

// Email
$f3->set('smtp', 
  new SMTP(
    $f3->get('AWM_EMAIL_SERVER'), 
    $f3->get('AWM_EMAIL_PORT'), 
    'ssl', 
    $f3->get('AWM_EMAIL_LOGIN'), 
    $f3->get('AWM_EMAIL_PASSWORD')
  )
); 


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

$f3->route('GET /register',
  function($f3) {
    $f3->set('page','register');

    echo \Template::instance()->render('templates/login.html');
  }
);

$f3->route('GET /recover-password',
  function($f3) {
    $f3->set('page','recover-password');
    
    echo \Template::instance()->render('templates/login.html');
  }
);

$f3->route('POST /proc-recover-password',
  function($f3) {
    $f3->set('page','proc-recover-password');
    
    $f3->set('res_recover', $f3->get('db')->exec('SELECT * FROM Usuarios WHERE email = ?', $f3->get('POST.inputEmail')));
		if($f3->get('db')->count() != 0){

			// Gerar nova senha
			$crypt = \Bcrypt::instance();
			$characters = '#$%&@=!0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';
			for ($i = 0; $i < 10; $i++) {
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}

			$f3->get('db')->exec('UPDATE Usuarios SET senha = "' . $crypt->hash($randomString, $f3->get('PW_SALT')) . 
        '" WHERE email = "' . $f3->get('POST.inputEmail') . '"');

			// Send the new password by email
			$f3->get('smtp')->set('To', '"' . $f3->get('res_recover')[0]['nome'] . '" <' . $f3->get('res_recover')[0]['email'] . '>');
			$f3->get('smtp')->set('From', $f3->get('AWM_EMAIL_FROM'));
			$f3->get('smtp')->set('Subject', '[Archives World Map] Your password was changed');
			$f3->get('smtp')->set('Errors-to', '<contato@yndexa.com>');
			$f3->get('smtp')->set('content-type','text/html;charset=utf-8');
			$f3->set('message', 'Olá' . ' ' . $f3->get('res_recover')[0]['nome'] . '!' .
            '<p>Your password at <strong>Archives World Map</strong> was changed.</p>' . 
            '<p>This message was generated by our platform.</p>' .
            '<p>Your new password is: ' . $randomString . '</p>' .
						'<p>Best regards,</p>' .
            '<p>Ricardo Sodré Andrade<br><a href="https://map.arquivista.net">https://map.arquivista.net</a></p>');
			$f3->set('sucessoemail', $f3->get('smtp')->send($f3->get('message')));

      
			if($f3->get('sucessoemail') == TRUE){
				$f3->set('emailerro', 'ok');
			} else {
				$f3->set('emailerro', 'falhaenvioemail');
			}			
      
		} else {
			$f3->set('emailerro', 'noemail');
		}
    
    echo \Template::instance()->render('templates/login.html');
  }
);

$f3->run();
