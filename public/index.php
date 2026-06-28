<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Core/Config.php';
require_once dirname(__DIR__) . '/src/Core/Database.php';
require_once dirname(__DIR__) . '/src/Core/Response.php';
require_once dirname(__DIR__) . '/src/Auth/Jwt.php';
require_once dirname(__DIR__) . '/src/Auth/Auth.php';
require_once dirname(__DIR__) . '/src/Repositories/CourseRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/ClassRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/PersonRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/ClassPeopleRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/LessonRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/StudentReportRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/StudentOpinionRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/SettingsRepository.php';
require_once dirname(__DIR__) . '/src/Services/OpenAITranscriptionService.php';
require_once dirname(__DIR__) . '/src/Services/OpenAIOpinionService.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/api')) {
    handleApi($path);
}

readfile(__DIR__ . '/index.html');

function handleApi(string $path): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input') ?: '[]', true);
    $input = is_array($input) ? $input : [];

    if ($path === '/api/login' && $method === 'POST') {
        $user = Auth::attempt((string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''));

        if ($user === null) {
            Response::error('E-mail ou senha invalidos.', 422);
        }

        Response::json([
            'user' => $user,
            'token' => Auth::tokenFor($user),
        ]);
    }

    if ($path === '/api/me' && $method === 'GET') {
        Response::json(['user' => Auth::requireUser()]);
    }

    if ($path === '/api/branding' && $method === 'GET') {
        Response::json(['data' => (new SettingsRepository())->branding()]);
    }

    if ($path === '/api/settings' && $method === 'GET') {
        Auth::requireRole(['admin']);
        Response::json(['data' => (new SettingsRepository())->publicSettings()]);
    }

    if ($path === '/api/settings/openai' && $method === 'PUT') {
        Auth::requireRole(['admin']);
        $settings = new SettingsRepository();
        $apiKey = trim((string) ($input['api_key'] ?? ''));
        $model = trim((string) ($input['model'] ?? 'gpt-5.5'));
        $prompt = trim((string) ($input['opinion_prompt'] ?? ''));
        $logoData = trim((string) ($input['app_logo_data'] ?? ''));
        $removeLogo = (bool) ($input['remove_logo'] ?? false);

        if ($apiKey !== '') {
            $settings->set('openai_api_key', $apiKey);
        }

        if ($model !== '') {
            $settings->set('openai_model', $model);
        }

        if ($prompt !== '') {
            $settings->set('openai_opinion_prompt', $prompt);
        }

        if ($removeLogo) {
            $settings->delete('app_logo_data');
        } elseif ($logoData !== '') {
            validateLogoData($logoData);
            $settings->set('app_logo_data', $logoData);
        }

        Response::json(['data' => $settings->publicSettings()]);
    }

    if ($path === '/api/courses' && $method === 'GET') {
        Auth::requireUser();
        Response::json(['data' => (new CourseRepository())->all()]);
    }

    if ($path === '/api/courses' && $method === 'POST') {
        Auth::requireRole(['admin']);
        validateCourse($input);
        try {
            Response::json(['data' => (new CourseRepository())->create($input)], 201);
        } catch (PDOException) {
            Response::error('Ja existe um curso com esse nome.', 422);
        }
    }

    if (preg_match('#^/api/courses/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $repository = new CourseRepository();

        if ($method === 'PUT') {
            Auth::requireRole(['admin']);
            validateCourse($input);
            try {
                $course = $repository->update($id, $input);
            } catch (PDOException) {
                Response::error('Ja existe um curso com esse nome.', 422);
            }

            if ($course === null) {
                Response::error('Curso nao encontrado.', 404);
            }

            Response::json(['data' => $course]);
        }

        if ($method === 'DELETE') {
            Auth::requireRole(['admin']);
            Response::json(['deleted' => $repository->delete($id)]);
        }
    }

    if ($path === '/api/classes' && $method === 'GET') {
        Auth::requireUser();
        Response::json(['data' => (new ClassRepository())->all()]);
    }

    if ($path === '/api/classes' && $method === 'POST') {
        Auth::requireRole(['admin']);
        validateClassInput($input);

        try {
            Response::json(['data' => (new ClassRepository())->create($input)], 201);
        } catch (PDOException) {
            Response::error('Ja existe uma classe com esse nome neste curso.', 422);
        }
    }

    if (preg_match('#^/api/classes/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $repository = new ClassRepository();

        if ($method === 'PUT') {
            Auth::requireRole(['admin']);
            validateClassInput($input);

            try {
                $class = $repository->update($id, $input);
            } catch (PDOException) {
                Response::error('Ja existe uma classe com esse nome neste curso.', 422);
            }

            if ($class === null) {
                Response::error('Classe nao encontrada.', 404);
            }

            Response::json(['data' => $class]);
        }

        if ($method === 'DELETE') {
            Auth::requireRole(['admin']);
            Response::json(['deleted' => $repository->delete($id)]);
        }
    }

    if (preg_match('#^/api/classes/(\d+)/people$#', $path, $matches)) {
        $id = (int) $matches[1];
        $repository = new ClassPeopleRepository();

        if ($method === 'GET') {
            Auth::requireUser();

            if ((new ClassRepository())->find($id) === null) {
                Response::error('Classe nao encontrada.', 404);
            }

            Response::json(['data' => $repository->get($id)]);
        }

        if ($method === 'PUT') {
            Auth::requireRole(['admin']);
            Response::json([
                'data' => $repository->sync(
                    $id,
                    is_array($input['students'] ?? null) ? $input['students'] : [],
                    is_array($input['teachers'] ?? null) ? $input['teachers'] : []
                ),
            ]);
        }
    }

    if ($path === '/api/secretaria/month' && $method === 'GET') {
        Auth::requireRole(['admin', 'secretaria']);
        $month = trim((string) ($_GET['month'] ?? ''));

        if ($month === '') {
            Response::error('Informe o mes.', 422);
        }

        Response::json(['data' => (new LessonRepository())->markersForMonth($month)]);
    }

    if ($path === '/api/secretaria/lesson' && $method === 'GET') {
        Auth::requireRole(['admin', 'secretaria']);
        $classId = (int) ($_GET['class_id'] ?? 0);
        $lessonDate = trim((string) ($_GET['lesson_date'] ?? ''));

        if ($classId <= 0 || $lessonDate === '') {
            Response::error('Informe a classe e a data.', 422);
        }

        Response::json(['data' => (new LessonRepository())->getWorkArea($classId, $lessonDate)]);
    }

    if ($path === '/api/secretaria/lesson' && $method === 'PUT') {
        Auth::requireRole(['admin', 'secretaria']);
        Response::json(['data' => (new LessonRepository())->save($input)]);
    }

    if ($path === '/api/pedagogico/reports' && $method === 'GET') {
        Auth::requireRole(['admin', 'pedagogico', 'professor']);
        $classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : null;
        $studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : null;
        Response::json(['data' => (new StudentReportRepository())->all($classId, $studentId)]);
    }

    if ($path === '/api/pedagogico/students' && $method === 'GET') {
        Auth::requireRole(['admin', 'pedagogico', 'professor']);
        Response::json(['data' => (new StudentReportRepository())->students()]);
    }

    if (preg_match('#^/api/pedagogico/students/(\d+)/classes$#', $path, $matches) && $method === 'GET') {
        Auth::requireRole(['admin', 'pedagogico', 'professor']);
        Response::json(['data' => (new StudentReportRepository())->classesForStudent((int) $matches[1])]);
    }

    if (preg_match('#^/api/pedagogico/students/(\d+)/opinion$#', $path, $matches) && $method === 'GET') {
        $user = Auth::requireRole(['admin', 'pedagogico', 'professor']);
        Response::json(['data' => (new StudentReportRepository())->opinionForStudent((int) $matches[1], $user)]);
    }

    if (preg_match('#^/api/pedagogico/students/(\d+)/opinions$#', $path, $matches) && $method === 'GET') {
        Auth::requireRole(['admin', 'pedagogico', 'professor']);
        Response::json(['data' => (new StudentOpinionRepository())->allForStudent((int) $matches[1])]);
    }

    if ($path === '/api/pedagogico/reports' && $method === 'POST') {
        $user = Auth::requireRole(['admin', 'pedagogico', 'professor']);
        validateStudentReportInput($input);
        Response::json(['data' => (new StudentReportRepository())->create($input, $user)], 201);
    }

    if (preg_match('#^/api/pedagogico/reports/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $repository = new StudentReportRepository();

        if ($method === 'PUT') {
            Auth::requireRole(['admin', 'pedagogico', 'professor']);
            validateStudentReportInput($input);
            $report = $repository->update($id, $input);

            if ($report === null) {
                Response::error('Relatorio nao encontrado.', 404);
            }

            Response::json(['data' => $report]);
        }

        if ($method === 'DELETE') {
            Auth::requireRole(['admin', 'pedagogico']);
            Response::json(['deleted' => $repository->delete($id)]);
        }
    }

    if ($path === '/api/pedagogico/transcribe' && $method === 'POST') {
        Auth::requireRole(['admin', 'pedagogico', 'professor']);
        Response::json(['text' => (new OpenAITranscriptionService())->transcribe($_FILES['audio'] ?? [])]);
    }

    if ($path === '/api/pedagogico/transcribe/status' && $method === 'GET') {
        Auth::requireRole(['admin', 'pedagogico', 'professor']);
        Response::json([
            'configured' => ((new SettingsRepository())->get('openai_api_key') ?: getenv('OPENAI_API_KEY') ?: '') !== '',
            'curl' => extension_loaded('curl'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ]);
    }

    if ($path === '/api/people' && $method === 'GET') {
        Auth::requireUser();
        Response::json(['data' => (new PersonRepository())->all()]);
    }

    if ($path === '/api/people' && $method === 'POST') {
        Auth::requireRole(['admin']);
        validatePersonInput($input);
        Response::json(['data' => (new PersonRepository())->create($input)], 201);
    }

    if (preg_match('#^/api/people/(\d+)$#', $path, $matches)) {
        $id = (int) $matches[1];
        $repository = new PersonRepository();

        if ($method === 'PUT') {
            Auth::requireRole(['admin']);
            validatePersonInput($input);
            $person = $repository->update($id, $input);

            if ($person === null) {
                Response::error('Pessoa nao encontrada.', 404);
            }

            Response::json(['data' => $person]);
        }

        if ($method === 'DELETE') {
            Auth::requireRole(['admin']);
            Response::json(['deleted' => $repository->delete($id)]);
        }
    }

    Response::error('Rota nao encontrada.', 404);
}

function validateCourse(array $input): void
{
    if (trim((string) ($input['name'] ?? '')) === '') {
        Response::error('Informe o nome do curso.', 422);
    }
}

function validateClassInput(array $input): void
{
    $courseId = (int) ($input['course_id'] ?? 0);

    if ($courseId <= 0) {
        Response::error('Selecione o curso.', 422);
    }

    if ((new CourseRepository())->find($courseId) === null) {
        Response::error('Curso nao encontrado.', 422);
    }

    if (trim((string) ($input['name'] ?? '')) === '') {
        Response::error('Informe o nome da classe.', 422);
    }
}

function validatePersonInput(array $input): void
{
    if (trim((string) ($input['name'] ?? '')) === '') {
        Response::error('Informe o nome da pessoa.', 422);
    }

    $email = trim((string) ($input['email'] ?? ''));

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        Response::error('Informe um e-mail valido.', 422);
    }
}

function validateStudentReportInput(array $input): void
{
    if ((int) ($input['class_id'] ?? 0) <= 0) {
        Response::error('Selecione a classe.', 422);
    }

    if ((int) ($input['student_person_id'] ?? 0) <= 0) {
        Response::error('Selecione o aluno.', 422);
    }

    if (trim((string) ($input['title'] ?? '')) === '') {
        Response::error('Informe o titulo do relatorio.', 422);
    }

    if (trim((string) ($input['body'] ?? '')) === '') {
        Response::error('Informe o texto do relatorio.', 422);
    }

    $date = trim((string) ($input['report_date'] ?? ''));

    if (!DateTimeImmutable::createFromFormat('Y-m-d', $date)) {
        Response::error('Informe uma data valida.', 422);
    }
}

function validateLogoData(string $logoData): void
{
    if (strlen($logoData) > 850000) {
        Response::error('A logomarca deve ter no maximo 600 KB.', 422);
    }

    if (!preg_match('#^data:image/(png|jpeg|webp|svg\+xml);base64,#', $logoData)) {
        Response::error('Envie uma logomarca em PNG, JPG, WebP ou SVG.', 422);
    }
}
