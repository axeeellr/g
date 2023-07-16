<?php

require 'vendor/autoload.php';
include 'connection.php';

$error = "";
$mensaje = "";
$submensaje = "";
session_start();




/****** G E T   U R L ******/
//despues del login redirecciono a la pagina donde estaba cuando le dió click al login
if (isset($_POST['goLogin'])) {
    $_SESSION['host'] = $_SERVER["HTTP_HOST"];
    $_SESSION['url'] = $_SERVER["REQUEST_URI"];
    header ('Location: login.php', true, 303);
}




/****** G O O G L E   L O G I N ******/
$clientID = '765073225137-1gggl6d22ovr6ne69sp9qdil8p6hb980.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/SivarTour/profile.php';

// create Client Request to access Google API
$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

//si google me envía 'code' por url
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);

    // get profile info
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();

    //array con la info que almacenaré en la db
    $userInfo = [
        'name' => $google_account_info['name'],
        'email' => $google_account_info['email'],
        'img' => $google_account_info['picture'],
        'token' => $google_account_info['id']
    ];

    //validar si ya está registrado
    $sql = "SELECT * FROM users WHERE email = '{$userInfo['email']}'";
    $run = mysqli_query($connection, $sql);

    //si ya está registrado
    if (mysqli_num_rows($run)) {
        $userInfo = mysqli_fetch_assoc($run);
        $token = $userInfo['token'];
        $id = $userInfo['id'];

    } else {
        //si no está registrado
        $sqll = "INSERT INTO users (name, email, password, img, token) VALUES ('{$userInfo['name']}', '{$userInfo['email']}', '', '{$userInfo['img']}', '{$userInfo['token']}')";
        $run = mysqli_query($connection, $sqll);

        if ($run) {
            $token = $userInfo['token'];
            $id = $userInfo['id'];
        }else {
            $error = "No se ha podido crear el usuario";
            die();
        }
    }

    $_SESSION['user_token'] = $token;
    $_SESSION['user_id'] = $id;
    $_SESSION['isLogin'] = 'si';
}




/****** L O G I N ******/
if(isset($_POST['login'])) {

    $correo = $_POST['email']; 
    $contraseña = $_POST['password'];

    $consulta_sql = mysqli_query($connection, "SELECT * FROM users WHERE email = '$correo' AND password = '$contraseña'");

    $nums = mysqli_num_rows($consulta_sql);
    $data = mysqli_fetch_assoc($consulta_sql);
    
    if($nums == 1){
        $token = $data['token'];
        $id = $data['id'];
        $_SESSION['isLogin'] = 'si';
        $_SESSION['user_token'] = $token;
        $_SESSION['user_id'] = $id;
        header('Location: ' . $_SESSION['url']);
    }else if ($nums == 0){
        $error = "Los datos no coinciden";
    }

}




/****** R E G I S T R O ******/
if(isset($_POST['register'])) {

    $nombre = $_POST['name']; 
    $correo = $_POST['email']; 
    $contraseña = $_POST['password'];
    $token = substr(str_shuffle(str_repeat('0123456789', 3)), 0, 21);

    //preparo mi consulta sql para el ingreso de los valores en la base de datos
    $consulta_sql =  "INSERT INTO users (name, email, password, token) VALUES('$nombre', '$correo', '$contraseña', '$token')";

    //verifico que correo no se repita en la base de datos
    $validarCorreo = mysqli_query($connection, "SELECT * FROM users WHERE email = '$correo'"); 
    
    //validacion de correo existente
    if(mysqli_num_rows($validarCorreo) == 1){
        $error = "Correo electrónico ya registrado";

    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $contraseña)) {
        $error = "Solo se permiten letras y números";

    } elseif (strlen($contraseña) > 15) {
        $error = "El máximo de caracteres es 15";

    } elseif (strlen($contraseña) < 5){
        $error = "El mínimo de caracteres es 5";

    } else {
        $resultado = mysqli_query($connection, $consulta_sql);

        $data = mysqli_fetch_assoc($validarCorreo);
        $id = $data['id'];
        
        if($resultado){
            $_SESSION['isLogin'] = 'si';
            $_SESSION['user_token'] = $token;
            $_SESSION['user_id'] = $id;
            header('Location: ' . $_SESSION['url']);
        }else{
            $error = "Ha ocurrido un error";
        }
    }
}




/****** A G R E G A R   C O M E N T A R I O ******/
if (isset($_POST['newComment'])) {
    $comment = $_POST['comment'];
    $id = $_SESSION['user_id'];

    $query = "INSERT INTO comments (id_user, comment) VALUES ('$id','$comment')";

    if (mysqli_query($connection, $query)) {
        header('Location: place.php', true, 303);
    } else {
        echo "Error al guardar el comentario: " . mysqli_error($connection);
    }
}




/****** V E R I F I C A R   C O D I G O ******/
if (isset($_POST['veriCode'])) {
    $code = $_POST['code'];

    $sql = "SELECT * FROM users WHERE token = '{$_SESSION['user_token']}'";
    $run = mysqli_query($connection, $sql);

    if ($row = mysqli_fetch_assoc($run)) {
        $codedb = $row['code']; // Código almacenado en la base de datos
    
        if ($code == $codedb) {
            $mensaje = "Tu cuenta ha sido activada";
            $submensaje = "Ya puedes usar todas las funcionalidades de nuestro sitio web";
            $sqll = "UPDATE users SET verified = 1 WHERE token = '{$_SESSION['user_token']}'";
            $runn = mysqli_query($connection, $sqll);
        } else {
            $mensaje = "Código incorrecto";
            $submensaje = "Ocurrió un error, intenta nuevamente";
        }
    }
}




if (isset($_POST['userData'])) {
    $username = $_POST['username'];
    $age = $_POST['age'];
    $sex = $_POST['sex'];
    $number = $_POST['number'];
    $address = $_POST['address'];
    $language = $_POST['language'];

    $sql = "UPDATE users SET";
    $updates = array();

    if (!empty($username)) {
        $updates[] = "username = '$username'";
    }

    if (!empty($age)) {
        $updates[] = "age = '$age'";
    }

    if (!empty($sex)) {
        $updates[] = "sex = '$sex'";
    }

    if (!empty($number)) {
        $updates[] = "number = '$number'";
    }

    if (!empty($address)) {
        $updates[] = "address = '$address'";
    }

    if (!empty($language)) {
        $updates[] = "language = '$language'";
    }

    if (!empty($updates)) {
        $sql .= " " . implode(", ", $updates);
        $sql .= " WHERE token = '{$_SESSION['user_token']}'";
        $run = mysqli_query($connection, $sql);
        header('Location: profile.php', true, 303);
    }

}




if (isset($_POST['newPlace'])) {
    echo "si";
}
?>