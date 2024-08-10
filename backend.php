<?php

// Configuración de la base de datos
$host = 'localhost'; 
$db = 'appcitas'; 
$user = 'appcitas'; 
$pass = 'appcitas_2024';

// Conectar a la base de datos
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
    exit;
}

// Obtener datos JSON del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action'])) {
    echo json_encode(['error' => 'Acción no especificada']);
    exit;
}

$action = $input['action'];

if ($action === 'validateDni' && isset($input['dni'])) {
    $dni = $input['dni'];

    // Validar el DNI
    if (empty($dni)) {
        echo json_encode(['error' => 'El DNI no puede estar vacío']);
        exit;
    }

    // Consultar la base de datos para verificar si el DNI existe
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE dni = ?');
    $stmt->execute([$dni]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        // DNI existe, permitir revisión
        echo json_encode(['exists' => true]);
    } else {
        // DNI no existe, asignar primera consulta
        echo json_encode(['exists' => false]);
    }
} elseif ($action === 'createAppointment' && isset($input['name'], $input['dni'], $input['phone'], $input['email'], $input['type_appointment'])) {
    $name = $input['name'];
    $dni = $input['dni'];
    $phone = $input['phone'];
    $email = $input['email'];
    $type_appointment = $input['type_appointment'];

    // Validar el DNI
    if (empty($dni)) {
        echo json_encode(['error' => 'El DNI no puede estar vacío']);
        exit;
    }

    // Verificar si el usuario ya existe en la tabla 'users'
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE dni = ?');
    $stmt->execute([$dni]);
    $userExists = $stmt->fetchColumn();

    if (!$userExists) {
        // Si el usuario no existe, crear un nuevo registro en la tabla 'users'
        $stmt = $pdo->prepare('INSERT INTO users (dni, name, phone, email) VALUES (?, ?, ?, ?)');
        $stmt->execute([$dni, $name, $phone, $email]);
    }

    // Verificar que el tipo de cita existe
    $stmt = $pdo->prepare('SELECT id FROM appointment_types WHERE type_name = ?');
    $stmt->execute([$type_appointment]);
    $appointmentTypeId = $stmt->fetchColumn();

    if (!$appointmentTypeId) {
        echo json_encode(['error' => 'Tipo de cita no válido']);
        exit;
    }

    // Obtener la próxima fecha y hora disponible
    $nextSlot = findNextAvailableSlot($pdo);

    // Insertar la cita en la base de datos
    $stmt = $pdo->prepare('INSERT INTO appointments (user_dni, appointment_type_id, appointment_date, appointment_time) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $dni,
        $appointmentTypeId,
        $nextSlot->format('Y-m-d'),
        $nextSlot->format('H:i:s')
    ]);

    // Enviar email de confirmación (simulación)
    $to = $email;
    $subject = "Confirmación de Cita";
    $message = "Hola $name,\n\nTu cita de tipo '$type_appointment' ha sido agendada para el día " . $nextSlot->format('d/m/Y') . " a las " . $nextSlot->format('H:i') . ".\n\nGracias.";
    $headers = "From: citas@ejemplo.com";

    // mail($to, $subject, $message, $headers); //

    echo json_encode(['success' => true, 'fecha' => $nextSlot->format('Y-m-d'), 'hora' => $nextSlot->format('H:i:s')]);
} else {
    echo json_encode(['error' => 'Datos incompletos o acción no válida']);
}



function findNextAvailableSlot($pdo) {
    $currentDate = new DateTime();

    // Configura la hora de inicio de la jornada (10:00 AM)
    $startOfDay = new DateTime();
    $startOfDay->setTime(10, 0);

    // Si la hora actual es antes del inicio de la jornada, ajusta la fecha a la hora de inicio
    if ($currentDate < $startOfDay) {
        $currentDate = $startOfDay;
    } else {
        $currentDate->setTime($currentDate->format('H'), 0);
    }
    

    while (true) {
        // Revisar si hay una cita en este horario
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND appointment_time = ?');
        $stmt->execute([$currentDate->format('Y-m-d'), $currentDate->format('H:i:s')]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            // No hay citas en este horario, este es el próximo disponible
            return $currentDate;
        }

        // Mover a la siguiente hora
        $currentDate->modify('+1 hour');

        // Si llegamos a las 10 PM, mover al siguiente día
        if ($currentDate->format('H') >= 22) {
            $currentDate->modify('+1 day');
            $currentDate->setTime(10, 0); // Reiniciar a 10:00 AM
        }
    }
}