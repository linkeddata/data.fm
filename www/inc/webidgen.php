<?php

// create a WebID certificate + profile data
if (isset($_POST['SPKAC']) && isset($_POST['username'])) {
    // Prepare the request
    $name = (isset($_POST['name']))?$_POST['name']:'Anonymous';
    
    if (isset($_POST['path'])) {
        $_POST['path'] = (substr($_POST['path'], 0, 1) == '/')?substr($_POST['path'], 1):$_POST['path'];
        $path = $_POST['path'];
    } else {
        $path = 'profile/card#me';
    }
    // Exit if we don't have a #
    if (strpos($path, '#') === false) // missing # 
        die("You must at least provide a # fragment. For example: #me or #public.");

    // remove the # fragment so we get the profile document path
    $path_frag = explode('#', $path);
    $profile = $path_frag[0];
    $hash = $path_frag[1];
    $_root = $_ENV['CLOUD_DATA'].'/'.$_POST['username'].'.'.ROOT_DOMAIN;
    
    // rebuild path for the profile document
    $webid_file = $_root.'/'.$profile;

    // create but do not overwrite existing profile document
    if (file_exists($webid_file) === true) {
        die('Error: <strong>'. $path.'</strong> already exists!');
    } else {           
        // check if the root dir exists and create it (recursively) if it doesn't
        if (!mkdir(dirname($webid_file), 0755, true))
            die('Cannot create directory at '.dirname($webid_file).', please check permissions.');
    }

    $BASE = 'https://'.$_POST['username'].'.'.$_SERVER['SERVER_NAME']; // force https
    $email = isset($_POST['email'])?$_POST['email']:null;
    $pic = isset($_POST['img'])?$_POST['img']:null;
    $spkac = str_replace(str_split("\n\r"), '', $_POST['SPKAC']);
    $webid = $BASE.'/'.$path;
    
    // --- Cert ---
    $cert_cmd = 'python '.$_ENV['CLOUD_HOME'].'/py/pki.py '.
                    " -s '$spkac'" .
                    " -n '$name'" .
                    " -w '$webid'";
    
    // Send the certificate back to the user
    header('Content-Type: application/x-x509-user-cert');
    
    $cert = trim(shell_exec($cert_cmd));
    $ret_cmd = "echo '$cert' | openssl x509 -in /dev/stdin -outform der";
    echo trim(shell_exec($ret_cmd));

    $mod_cmd = "echo '$cert' | openssl x509 -in /dev/stdin -modulus -noout";
    // remove the Modulus= part
    $output = explode('=', trim(shell_exec($mod_cmd)));
    $modulus = $output[1];
    
    // --- Workspaces ---
    // create shared storage space
    $storage_uri = $BASE.'/storage/';
    $storage_file = $_root.'/storage/';
    if (!mkdir($storage_file, 0755, true))
        die('Cannot create storage space "'.$storage_file.'", please check permissions');   
    // end workspaces

    // --- Profile --- 
    // Write the new profile to disk
    $document = new Graph('', $webid_file, '', $BASE.'/'.$profile);
    if (!$document) {
        echo "Cannot create a new graph!";
        exit;
    }
    
    // add a PrimaryTopic
    $document->append_objects($BASE.'/'.$profile,
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
             array(array('type'=>'uri', 'value'=>'http://xmlns.com/foaf/0.1/PersonalProfileDocument')));
    $document->append_objects($BASE.'/'.$profile,
            'http://xmlns.com/foaf/0.1/primaryTopic',
             array(array('type'=>'uri', 'value'=>$webid)));
     
    // add a foaf:Person
    $document->append_objects($webid,
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
            array(array('type'=>'uri', 'value'=>'http://xmlns.com/foaf/0.1/Person')));
    // add name
    $document->append_objects($webid,
            'http://xmlns.com/foaf/0.1/name',
            array(array('type'=>'literal', 'value'=>$name)));
    // add mbox if we have one
    if (strlen($email) > 0) {
        $document->append_objects($webid,
                'http://xmlns.com/foaf/0.1/mbox',
                array(array('type'=>'uri', 'value'=>'mailto:'.$email)));
    }
    // add avatar if we have one
    if (strlen($pic) > 0) {
        $document->append_objects($webid,
                'http://xmlns.com/foaf/0.1/img',
                array(array('type'=>'uri', 'value'=>$pic)));
    }
    // ---- Add workspaces ----
    // add shared storage space
    $document->append_objects($webid,
            'http://www.w3.org/ns/pim/space#storage',
            array(array('type'=>'uri', 'value'=>$storage_uri)));
    
    // ---- Certificate ----
    // add modulus and exponent as bnode
    $document->append_objects($webid,
            'http://www.w3.org/ns/auth/cert#key',
            array(array('type'=>'bnode', 'value'=>'_:bnode1')));
    $document->append_objects('_:bnode1',
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
            array(array('type'=>'uri', 'value'=>'http://www.w3.org/ns/auth/cert#RSAPublicKey'))); 
    
    if (isset($modulus))
    $document->append_objects('_:bnode1',
            'http://www.w3.org/ns/auth/cert#modulus',
            array(array('type'=>'literal', 'value'=>$modulus, 'datatype'=>'http://www.w3.org/2001/XMLSchema#hexBinary')));
    
    $document->append_objects('_:bnode1',
            'http://www.w3.org/ns/auth/cert#exponent',
            array(array('type'=>'literal', 'value'=>'65537', 'datatype'=>'http://www.w3.org/2001/XMLSchema#int')));
    
    $document->save();
    
    // ------ DONE WITH PROFILE -------
}

