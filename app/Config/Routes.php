<?php

use CodeIgniter\Router\RouteCollection;



// $routes->get('/', 'Home::index');
// API's AUTH

//login
$routes->get('/auth/login', 'Auth::login');
$routes->post('/auth/login_submit', 'Auth::login_submit');

//logout
$routes->post('/auth/logout', 'Auth::logout');

// new account
$routes->get('/auth/newAccount', 'Auth::newAccount');
$routes->post('/auth/newAccount_submit', 'Auth::newAccount_submit');

// forgot password
$routes->get('/auth/forgotPassword', 'Auth::forgotPassword');
$routes->post('/auth/forgotPassword_submit', 'Auth::forgotPassword_submit');

// dashboard
$routes->get('/main', 'Main::index');

// JWT Config
$routes->resource('api/auth', ['controller' => 'Auth']);
$routes->resource('api/user', ['controller' => 'User']);


// external API CREDITAPP APP MOBILE
$routes->post('hola', 'AppTracker::hola');
$routes->post('api/v2/verifyToken', 'Auth::verifyToken');
$routes->post('api/v2/getToken', 'Auth::createToken');
$routes->get('api/v2/getClientes', 'Functions::getClientes');
$routes->post('api/v2/crearCliente', 'Functions::guardarCliente');
    
// creditos
$routes->get('api/v2/getCreditos', 'Functions::getCreditos');
$routes->get('api/v2/resumen', 'CreditoController::obtenerResumenNegocio');
$routes->post('api/v2/getCreditoXId', 'Functions::getCreditosXid');
$routes->post('api/v2/creditos/crear', 'CreditoController::crearCredito');
$routes->post('api/v2/realizarPago', 'CreditoController::guardarPago');
$routes->post('api/v2/eliminarCredito', 'CreditoController::eliminarCredito');
$routes->post('api/v2/eliminarCliente', 'CreditoController::eliminarCliente');
$routes->post('api/v2/credito/abonar', 'CreditoController::abonar');
$routes->post('api/v2/credito/eliminar/pago', 'CreditoController::eliminarPago');
$routes->post('api/v2/credito/nuevoPago', 'CreditoController::registrarPago');
$routes->post('api/v2/creditosCliente', 'CreditoController::creditosCliente');
$routes->post('api/v2/cobranzasCredito', 'CreditoController::cobranzasCredito');
$routes->post('api/v2/getPlanPagos', 'Functions::planPagos');
