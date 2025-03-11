<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rossi equipamientos - Nueva solicitud web</title>
</head>
<body>
    <h3>Hola, te informamos que se ha enviado una nueva solicitud desde el formulario web. Los datos ingresados son:</h3>
    <p><strong>Nombre:</strong> {{ $data['name'] }}</p>
    <p><strong>Empresa:</strong> {{ $data['company'] }}</p>
    <p><strong>Provincia:</strong> {{ $data['province'] }}</p>
    <p><strong>Localidad:</strong> {{ $data['locality'] }}</p>
    <p><strong>Teléfono:</strong> {{ $data['phone'] }}</p>
    <p><strong>Email:</strong> {{ $data['email'] }}</p>
    <p><strong>Mensaje:</strong> {{ $data['message'] }}</p>
	<p>Por favor, no tardes en responderle.<br>Muchas gracias</p>
	<p>Rossi equipamientos.</p>
</body>
</html>
