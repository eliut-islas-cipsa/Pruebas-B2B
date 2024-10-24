<?php

# para cada id de compra en el archivo compras.txt
# se busca si aparece en algun log de la carpeta logs
# si aparece se imprime en color VERDE desde la primera aparicion hasta la linea que contiene "Se termina peticion para crear orden con idVtex"
# si no aparece se imprime en color ROJO "No se encontraron logs para la compra con id: $idCompra" 



// Función para imprimir texto en color
function printColor($text, $color) {
    $colors = [
        'green' => "\033[32m",  // Verde
        'blue' => "\033[34m",   // Azul
        'purple' => "\033[35m", // Morado
        'cyan' => "\033[36m",   // Cyan
        'red' => "\033[31m",    // Rojo
        'reset' => "\033[0m"    // Reset de color
    ];

    echo $colors[$color] . $text . $colors['reset'] . PHP_EOL;
}


// Función para convertir el color a HTML
function getHtmlColor($color) {
    $colors = [
        'green' => 'green',
        'blue' => 'blue',
        'purple' => 'purple',
        'cyan' => 'cyan',
        'red' => 'red',
        'reset' => 'black' // El color por defecto es negro
    ];

    return $colors[$color];
}

// Función para agregar contenido en un archivo HTML
function appendToHtml($htmlFile, $text, $color) {
    $htmlColor = getHtmlColor($color);
    file_put_contents($htmlFile, "<span style='color: $htmlColor;'>$text</span><br>\n", FILE_APPEND);
}


// Leer el archivo de compras
$comprasFile = 'compras.txt';
if (!file_exists($comprasFile)) {
    die("El archivo $comprasFile no existe.");
}

$compras = file($comprasFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Verificar cada compra en los logs
$logDir = 'logs/';
if (!is_dir($logDir)) {
    die("El directorio de logs no existe.");
}

foreach ($compras as $idCompra) {
    $idEncontrado = false;

    printColor("\n\n#### Buscando ID de compra: $idCompra", 'reset');

    // crea la carpeta logs_result si no existe
    if (!file_exists('logs_result')) {
        mkdir('logs_result');
    }

    $htmlFile = "logs_result/$idCompra.html"; // Nombre del archivo HTML para cada ID
    file_put_contents($htmlFile, "<html><body><h1>Logs para la compra con ID: $idCompra</h1>\n"); // Crear archivo HTML



    // Buscar en cada archivo de log
    foreach (glob($logDir . '*.log') as $logFile) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $foundStart = false;
        $color = 'reset'; // Inicialmente sin color

        $primer_IDCTEVTEX = true;
        foreach ($lines as $line) {
            // Buscar si el ID de compra aparece en la línea
            if (strpos($line, $idCompra) !== false) {
                $idEncontrado = true;
                $foundStart = true;
            }

            // Si el ID fue encontrado, procesamos las líneas
            if ($foundStart) {

                // se omiten las lieas que comienzan con "hh:ii:ss - Respuesta =" donde hh:ii:ss es la hora del log por ejemplo 07:28:53
                if (preg_match('/^\d{2}:\d{2}:\d{2} - Respuesta =/', $line)) {
                    continue;
                }

                // Cambios de color según el contenido de las líneas
                if (strpos($line, 'Valores structure ZVTEX_PEDIDO_H') !== false) {
                    $color = 'green';
                } elseif (strpos($line, 'orderDetailsData =') !== false) {
                    $color = 'blue';
                    continue;
                } elseif (strpos($line, 'Valores table ZVT_PARTNERS') !== false) {
                    $color = 'reset';
                } elseif (strpos($line, 'IDCTEVTEX') !== false) {
                    if ($primer_IDCTEVTEX) {
                        $color = 'purple';
                        $primer_IDCTEVTEX = false;
                    } else {
                        $color = 'cyan';
                    }
                } elseif (strpos($line, 'Se ejecuta RFC ZRFCSD_CREAR_PEDIDO') !== false) {
                    $color = 'reset';
                }

                // Imprimir la línea con el color actual
                printColor($line, $color);

                // Agregar la línea al archivo HTML con el color actual
                appendToHtml($htmlFile, htmlspecialchars($line), $color);

                // Detenerse al encontrar la línea que indica que la orden terminó
                if (strpos($line, 'Se termina peticion para crear orden con idVtex') !== false) {
                    break;
                }
            }
        }
    }

    // Si no se encontró el ID en ningún log, imprimir mensaje en rojo
    if (!$idEncontrado) {
        printColor("No se encontraron logs para la compra con id: $idCompra", 'red');
        appendToHtml($htmlFile, htmlspecialchars($errorMessage), 'red');
    }

    // Finalizar el archivo HTML
    file_put_contents($htmlFile, "</body></html>", FILE_APPEND);
}
