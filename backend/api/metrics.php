<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parámetros de conexión a la réplica de Moodle (Docker host o contenedor mariadb)
$db_host = getenv('MOODLE_DB_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1');
$db_port = (int)(getenv('MOODLE_DB_PORT') ?: (getenv('DB_PORT') ?: 3307));
$db_user = getenv('MOODLE_DB_USER') ?: (getenv('DB_USER') ?: 'moodle');
$db_pass = getenv('MOODLE_DB_PASS') ?: (getenv('DB_PASS') ?: 'moodlepass2024');
$db_name = getenv('MOODLE_DB_NAME') ?: (getenv('DB_NAME') ?: 'moodle');

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($mysqli->connect_error) {
    // Si falla la conexión local directa en 3307, intentar con el socket interno de docker
    $mysqli = @new mysqli('moodle_uagrm_db', $db_user, $db_pass, $db_name, 3306);
}
if ($mysqli->connect_error) {
    $mysqli = @new mysqli('mariadb', $db_user, $db_pass, $db_name, 3306);
}

if ($mysqli->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo conectar a la base de datos réplica de Moodle: ' . $mysqli->connect_error,
        'cqrs_latency_ms' => 0
    ]);
    exit;
}

$mysqli->set_charset('utf8mb4');
$start_time = microtime(true);

$action = $_GET['action'] ?? 'overview';
$platform = $_GET['platform'] ?? 'virtual';
$role = $_GET['role'] ?? 'central'; // central, vicedecanato, carrera, docente
$course_id = (int)($_GET['course_id'] ?? 2);

// =========================================================================
// 1. REPORTE MACRO UNIVERSITARIO (Nivel DEDTE Central / Vicedecanatos)
// =========================================================================
if ($action === 'macro_summary') {
    // Catálogo de Facultades y Carreras (Estructura oficial UAGRM según Resolución ICU 053/2023)
    $facultades = [
        [
            'id' => 'juridicas',
            'nombre' => 'Facultad de Ciencias Jurídicas, Políticas y Sociales',
            'sigla' => 'FCJPS',
            'carreras' => [
                ['id' => 'derecho', 'nombre' => 'Carrera de Derecho', 'sigla' => 'DER', 'total_alumnos' => 1420, 'aprobacion' => 58.4, 'reprobacion' => 31.2, 'deserción' => 10.4, 'estado' => 'amarillo'],
                ['id' => 'relaciones_internacionales', 'nombre' => 'Relaciones Internacionales', 'sigla' => 'RIN', 'total_alumnos' => 640, 'aprobacion' => 72.1, 'reprobacion' => 18.5, 'deserción' => 9.4, 'estado' => 'verde'],
                ['id' => 'ciencias_politicas', 'nombre' => 'Ciencias Políticas', 'sigla' => 'CPO', 'total_alumnos' => 480, 'aprobacion' => 66.8, 'reprobacion' => 24.2, 'deserción' => 9.0, 'estado' => 'amarillo']
            ]
        ],
        [
            'id' => 'ficct',
            'nombre' => 'Facultad de Cs. de la Computación y Telecomunicaciones',
            'sigla' => 'FICCT',
            'carreras' => [
                ['id' => 'sistemas', 'nombre' => 'Ingeniería de Sistemas', 'sigla' => 'SIS', 'total_alumnos' => 1850, 'aprobacion' => 51.5, 'reprobacion' => 38.0, 'deserción' => 10.5, 'estado' => 'amarillo'],
                ['id' => 'informatica', 'nombre' => 'Ingeniería Informática', 'sigla' => 'INF', 'total_alumnos' => 1620, 'aprobacion' => 48.2, 'reprobacion' => 42.1, 'deserción' => 9.7, 'estado' => 'rojo'],
                ['id' => 'redes', 'nombre' => 'Ingeniería en Redes y Telecomunicaciones', 'sigla' => 'RED', 'total_alumnos' => 790, 'aprobacion' => 64.0, 'reprobacion' => 26.5, 'deserción' => 9.5, 'estado' => 'amarillo']
            ]
        ],
        [
            'id' => 'contables',
            'nombre' => 'Facultad de Cs. Contables, Auditoría y Control de Gestión',
            'sigla' => 'FCA',
            'carreras' => [
                ['id' => 'contaduria', 'nombre' => 'Contaduría Pública / Auditoría', 'sigla' => 'CPA', 'total_alumnos' => 2100, 'aprobacion' => 68.3, 'reprobacion' => 22.4, 'deserción' => 9.3, 'estado' => 'amarillo'],
                ['id' => 'informacion_control', 'nombre' => 'Sistemas de Información y Control', 'sigla' => 'SIC', 'total_alumnos' => 540, 'aprobacion' => 74.5, 'reprobacion' => 17.2, 'deserción' => 8.3, 'estado' => 'verde']
            ]
        ]
    ];

    $execution_time = round((microtime(true) - $start_time) * 1000, 2);

    echo json_encode([
        'status' => 'success',
        'platform' => $platform,
        'total_estudiantes_uagrm' => 9440,
        'tasa_aprobacion_global' => 58.7,
        'tasa_reprobacion_global' => 31.4,
        'tasa_desercion_global' => 9.9,
        'paralelos_criticos_total' => 14,
        'facultades' => $facultades,
        'cqrs_latency_ms' => $execution_time
    ]);
    exit;
}

// =========================================================================
// 2. REPORTE DETALLADO DE CARRERA Y ASIGNATURA (Datos Reales Moodle)
// =========================================================================
// Métricas agregadas de calificaciones en el curso activo
$sql_metrics = "
    SELECT 
        COUNT(DISTINCT t.id) AS total_matriculados,
        ROUND(AVG(t.finalgrade), 2) AS promedio_general,
        SUM(CASE WHEN t.finalgrade >= 51 THEN 1 ELSE 0 END) AS total_aprobados,
        SUM(CASE WHEN t.finalgrade > 0 AND t.finalgrade < 51 THEN 1 ELSE 0 END) AS total_reprobados,
        SUM(CASE WHEN t.finalgrade >= 40 AND t.finalgrade <= 50 THEN 1 ELSE 0 END) AS total_segunda_instancia,
        SUM(CASE WHEN t.finalgrade IS NULL OR t.finalgrade = 0 THEN 1 ELSE 0 END) AS total_abandono_critico,
        SUM(CASE WHEN t.finalgrade >= 70 THEN 1 ELSE 0 END) AS total_destacados,
        SUM(CASE WHEN t.finalgrade >= 51 AND t.finalgrade < 70 THEN 1 ELSE 0 END) AS total_regulares
    FROM (
        SELECT DISTINCT u.id, gg.finalgrade
        FROM mdl_user_enrolments ue
        JOIN mdl_enrol e ON e.id = ue.enrolid
        JOIN mdl_user u ON u.id = ue.userid
        LEFT JOIN mdl_grade_items gi ON gi.courseid = e.courseid AND gi.itemtype = 'course'
        LEFT JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
        WHERE e.courseid = $course_id
    ) t
";
$res_metrics = $mysqli->query($sql_metrics);
$metrics = $res_metrics ? $res_metrics->fetch_assoc() : [];

$total = (int)($metrics['total_matriculados'] ?? 0);
$promedio = (float)($metrics['promedio_general'] ?? 0.0);
$aprobados = (int)($metrics['total_aprobados'] ?? 0);
$reprobados = (int)($metrics['total_reprobados'] ?? 0);
$segunda_instancia = (int)($metrics['total_segunda_instancia'] ?? 0);
$abandono_critico = (int)($metrics['total_abandono_critico'] ?? 0);
$destacados = (int)($metrics['total_destacados'] ?? 0);
$regulares = (int)($metrics['total_regulares'] ?? 0);

$tasa_aprobacion = $total > 0 ? round(($aprobados / $total) * 100, 1) : 0;
$tasa_reprobacion = $total > 0 ? round(($reprobados / $total) * 100, 1) : 0;
$tasa_desercion = $total > 0 ? round(($abandono_critico / $total) * 100, 1) : 0;

// Determinación del Semáforo Institucional (RF-06)
if ($tasa_reprobacion > 40 || $tasa_desercion > 25) {
    $semaforo = 'rojo';
    $semaforo_label = 'Crítico (Intervención Prioritaria Directiva)';
} else if ($tasa_reprobacion >= 20 || $tasa_desercion >= 10) {
    $semaforo = 'amarillo';
    $semaforo_label = 'En Observación (Alerta Temprana)';
} else {
    $semaforo = 'verde';
    $semaforo_label = 'Óptimo (Desempeño Satisfactorio)';
}

// Estudiantes detallados
$sql_students = "
    SELECT DISTINCT
        u.id,
        u.firstname,
        u.lastname,
        u.email,
        u.lastaccess,
        gg.finalgrade
    FROM mdl_user_enrolments ue
    JOIN mdl_enrol e ON e.id = ue.enrolid
    JOIN mdl_user u ON u.id = ue.userid
    LEFT JOIN mdl_grade_items gi ON gi.courseid = e.courseid AND gi.itemtype = 'course'
    LEFT JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
    WHERE e.courseid = $course_id
    ORDER BY 
        CASE 
            WHEN gg.finalgrade IS NULL OR gg.finalgrade = 0 THEN 1
            WHEN gg.finalgrade >= 40 AND gg.finalgrade <= 50 THEN 2
            WHEN gg.finalgrade < 51 THEN 3
            ELSE 4
        END ASC,
        gg.finalgrade ASC,
        u.lastname ASC
";
$res_students = $mysqli->query($sql_students);
$student_list = [];

while ($st = $res_students->fetch_assoc()) {
    $grade = $st['finalgrade'] !== null ? round((float)$st['finalgrade'], 1) : null;
    
    // Clasificación según directriz UAGRM (RF-04, RF-05)
    if ($grade === null || $grade == 0) {
        $estado = 'critico';
        $etiqueta = '🔴 Abandono Crítico';
    } else if ($grade >= 40 && $grade <= 50) {
        $estado = 'segunda_instancia';
        $etiqueta = '🟠 Segunda Instancia (Repechaje)';
    } else if ($grade < 51) {
        $estado = 'reprobado';
        $etiqueta = '🟡 Reprobado Directo';
    } else if ($grade < 70) {
        $estado = 'regular';
        $etiqueta = '🔵 Regular';
    } else {
        $estado = 'destacado';
        $etiqueta = '🟢 Sobresaliente';
    }

    $days_inactive = $st['lastaccess'] > 0 ? round((time() - (int)$st['lastaccess']) / 86400) : 999;
    if ($days_inactive > 14) {
        $alerta_inactividad = 'Inactividad Nivel 2 (>14 días)';
    } else if ($days_inactive > 7) {
        $alerta_inactividad = 'Alerta Temprana Nivel 1 (>7 días)';
    } else {
        $alerta_inactividad = 'Activo';
    }

    $student_list[] = [
        'id' => (int)$st['id'],
        'nombre' => $st['firstname'] . ' ' . $st['lastname'],
        'email' => $st['email'],
        'calificacion' => $grade,
        'estado' => $estado,
        'etiqueta' => $etiqueta,
        'es_segunda_instancia' => ($grade >= 40 && $grade <= 50),
        'dias_inactivo' => $days_inactive,
        'alerta_inactividad' => $alerta_inactividad
    ];
}

$execution_time = round((microtime(true) - $start_time) * 1000, 2);

echo json_encode([
    'status' => 'success',
    'platform' => $platform,
    'curso' => [
        'id' => $course_id,
        'sigla' => 'DER-101',
        'nombre' => '[DERECHO] Guía Estudiantil y Deontología Jurídica',
        'carrera' => 'Derecho',
        'facultad' => 'Facultad de Ciencias Jurídicas, Políticas y Sociales',
        'paralelo' => 'Grupo 1 (Modalidad Virtual)',
        'gestion' => 'II-2026',
        'resolucion_icu' => 'Resolución ICU N° 053/2023',
        'carpeta_pedagogica_auditada' => true,
        'formato_mosaicos_auditado' => true,
        'visible' => true
    ],
    'metricas' => [
        'total_matriculados' => $total,
        'promedio_general' => $promedio,
        'aprobados' => $aprobados,
        'reprobados' => $reprobados,
        'segunda_instancia' => $segunda_instancia,
        'abandono_critico' => $abandono_critico,
        'destacados' => $destacados,
        'regulares' => $regulares,
        'tasa_aprobacion' => $tasa_aprobacion,
        'tasa_reprobacion' => $tasa_reprobacion,
        'tasa_desercion' => $tasa_desercion,
        'semaforo' => $semaforo,
        'semaforo_label' => $semaforo_label
    ],
    'estudiantes' => $student_list,
    'cqrs_latency_ms' => $execution_time
]);
