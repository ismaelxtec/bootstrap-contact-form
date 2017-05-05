<?php

/*
	JJBM,   26/03/2009, captcha.php
		http://jose-juan.computer-mind.com/jose-juan/Captcha-PHP.php
		Genera una imágen CAPTCHA y almacena en la sesión el código establecido.	
		(Obviamente sólo puede usarse una imagen CAPTCHA por sesión a la vez).

	Ismael, 01/03/2017
		Use 'imagettftext' in spite of 'imagestring' with particular font and size.
		Added more security tricks: dots on image, use of numbers
		Font: http://www.1001freefonts.com/calligraphy.font
*/

// Configuración:
$N = 3;		// Nivel de emborronado { 2, 3, 4, ... }
$J = 100;	// Calidad JPEG { 0, 1, 2, 3, ..., 100 }
$M = 5;		// Margen.
$L = 2;		// Número de letras.
$C = FALSE;	// Case sensitive.

// Acceso a los objetos de sesión:
session_start();

// Indicamos que vamos a generar una imagen ¡no una página HTML!
header("Content-type: image/jpeg");

// Inicializamos cualquier posible valor previo de captcha:
$_SESSION['CAPTCHA'] = '';
// Metemos tantos caraceteres aleatorios como sean precisos:
for( $n = 0; $n < $L; $n++ ) {
	$_SESSION['CAPTCHA'] .= C();
	if ($n < ($L-1)) $_SESSION['CAPTCHA'] .= ' '; // Los espacios nos interesan solo a nivel visual! Luego se eliminan.
}

// Si no es case sensitive lo ponemos todo en minúsculas:
if( ! $C )
	$_SESSION['CAPTCHA'] = strtolower( $_SESSION['CAPTCHA'] );

// Dimensiones del captcha:
$w = 4 * $M + ($L+1) * imagefontwidth ( 5 );
$h = 4 * $M +      imagefontheight( 5 );

// Creamos una  imagen:
$im = imagecreatetruecolor( $w, $h );

// La rellenamos de blanco:
imagefill( $im, 0, 0, imagecolorallocate( $im, 255, 255, 255 ) );

// Elegimos aleatoriamente un ángulo de emborronado:
$A = ( rand() % 180 ) / 3.14;

// Realizamos iteraciones de emborronado:
for( $n = 0; $n < $N; $n++ ) {

	// Factor de interpolación, va de 1.0 a 0.0
	$t = 1.0 - $n / ( $N - 1.0 );

	// El radio se va centrando a medida que se hace nítido:
	$r = $M * $t;

	// El color va siendo cada vez más oscuro:
	$c = 255 * $t;
	$c = imagecolorallocate( $im, $c, $c, $c );

	// Trazamos dos líneas aleatorias para dificultar más las cosas:
	imageline( $im, $M, rand( $M, $h - $M ), $w - $M, rand( $M, $h - $M ), $c );
	imageline( $im, rand( $M, $w - $M ), $M, rand( $M, $w - $M ), $h - $M, $c );

	// Pasamos un filtro gaussiano:
	imagefilter( $im, IMG_FILTER_GAUSSIAN_BLUR );

	// Dibujamos el texto con 'imagestring' en el sentido del ángulo y radio de desplazamiento:
	//imagestring( $im, 5, $M + $r * cos( $A ), $M + $r * sin( $A ), $_SESSION['CAPTCHA'], $c );

	// Add the text with 'imagettftext' to setup font_size!
	// Replace path by your own font path
	$font = 'fonts/CalligraphyFLF.ttf';
	$font_size = 25;
	imagettftext($im, $font_size, 0,  $M + $r * cos( $A ), $M + ($h/2), $c , $font, $_SESSION['CAPTCHA']);

	// Pasamos otro filtro gaussiano:
	imagefilter( $im, IMG_FILTER_GAUSSIAN_BLUR );
}//for

// Add some noise (black dots) to the image:
$numDots = rand(15, 80);
$black = imageColorAllocate($img, 255, 255, 255);
for ($i = 0; $i < $numDots; $i++) {
	imagesetpixel($im, rand(0, $w),
	rand(0, $h), $black);
}



// Escribimos la imagen como un JPEG en el buffer de salida:
imagejpeg( $im, NULL, $J );
// Using imagepng() results in clearer text compared with imagejpeg()
//imagepng($im, NULL,$J); // Como PNG cuando queramos que sea de más calidad!

// Liberamos la imagen:
imagedestroy( $im );

// Eliminamos los espacios, pues solo nos interesan a nivel visual!
$_SESSION['CAPTCHA'] = str_replace(' ', '', $_SESSION['CAPTCHA']);

// Devuelve un caracter aleatorio:
function C() {
	$W = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz123456789";
	return substr( $W, rand() % strlen( $W ), 1 );
}

?>
