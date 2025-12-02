<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
    exit;
}

// Получение данных из запроса
$data = json_decode(file_get_contents('php://input'), true);

// Проверка reCAPTCHA
$recaptcha_secret = '6Lex5x4sAAAAAHNjkjgnekB4rdX-yt_LjvYPNWQ7'; // Секретный ключ reCAPTCHA
$recaptcha_response = isset($data['recaptcha_token']) ? $data['recaptcha_token'] : '';

if (empty($recaptcha_response)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA не пройдена']);
    exit;
}

// Верификация reCAPTCHA
$recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
$recaptcha_data = [
    'secret' => $recaptcha_secret,
    'response' => $recaptcha_response
];

$recaptcha_options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($recaptcha_data)
    ]
];

$recaptcha_context = stream_context_create($recaptcha_options);
$recaptcha_result = file_get_contents($recaptcha_url, false, $recaptcha_context);
$recaptcha_json = json_decode($recaptcha_result, true);

if (!$recaptcha_json || !isset($recaptcha_json['success']) || !$recaptcha_json['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ошибка проверки reCAPTCHA']);
    exit;
}

// Получение данных формы
$form_type = isset($data['form_type']) ? $data['form_type'] : 'unknown';
$to_email = 'salelockoutsystem@gmail.com';

// Формирование темы письма
$subject = '';
$message_body = '';

switch ($form_type) {
    case 'main':
        $subject = 'Новая заявка с главной формы - Получить подбор ЛАРН-набора';
        $message_body = "Новая заявка с главной формы\n\n";
        $message_body .= "Название компании: " . (isset($data['company-name']) ? htmlspecialchars($data['company-name']) : 'Не указано') . "\n";
        $message_body .= "Контактное лицо: " . (isset($data['contact-person']) ? htmlspecialchars($data['contact-person']) : 'Не указано') . "\n";
        $message_body .= "Телефон: " . (isset($data['phone']) ? htmlspecialchars($data['phone']) : 'Не указано') . "\n";
        $message_body .= "Email: " . (isset($data['email']) ? htmlspecialchars($data['email']) : 'Не указано') . "\n";
        $message_body .= "Регион / город: " . (isset($data['region']) ? htmlspecialchars($data['region']) : 'Не указано') . "\n";
        $message_body .= "Тип объекта: " . (isset($data['object-type']) ? htmlspecialchars($data['object-type']) : 'Не указано') . "\n";
        $message_body .= "Комментарий: " . (isset($data['comment']) ? htmlspecialchars($data['comment']) : 'Не указано') . "\n";
        break;
        
    case 'quick':
        $subject = 'Новая быстрая заявка';
        $message_body = "Новая быстрая заявка\n\n";
        $message_body .= "Телефон: " . (isset($data['quick-phone']) ? htmlspecialchars($data['quick-phone']) : 'Не указано') . "\n";
        $message_body .= "Имя: " . (isset($data['quick-name']) ? htmlspecialchars($data['quick-name']) : 'Не указано') . "\n";
        $message_body .= "Компания: " . (isset($data['quick-company']) ? htmlspecialchars($data['quick-company']) : 'Не указано') . "\n";
        $message_body .= "Комментарий: " . (isset($data['quick-comment']) ? htmlspecialchars($data['quick-comment']) : 'Не указано') . "\n";
        break;
        
    case 'docs':
        $subject = 'Запрос на скачивание документов';
        $message_body = "Запрос на скачивание документов\n\n";
        $message_body .= "Email: " . (isset($data['docs-email']) ? htmlspecialchars($data['docs-email']) : 'Не указано') . "\n";
        $message_body .= "Компания / должность: " . (isset($data['docs-company-position']) ? htmlspecialchars($data['docs-company-position']) : 'Не указано') . "\n";
        break;
        
    default:
        $subject = 'Новая заявка с сайта';
        $message_body = "Новая заявка\n\n";
        $message_body .= print_r($data, true);
}

$message_body .= "\n\n---\n";
$message_body .= "Дата отправки: " . date('d.m.Y H:i:s') . "\n";
$message_body .= "IP адрес: " . ($_SERVER['REMOTE_ADDR'] ?? 'Неизвестно') . "\n";

// Настройки для отправки письма
$headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
$headers .= "Reply-To: " . (isset($data['email']) ? $data['email'] : (isset($data['docs-email']) ? $data['docs-email'] : 'noreply@' . $_SERVER['HTTP_HOST'])) . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Отправка письма
$mail_sent = mail($to_email, $subject, $message_body, $headers);

if ($mail_sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Заявка успешно отправлена!'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при отправке письма. Попробуйте позже.'
    ]);
}
?>

