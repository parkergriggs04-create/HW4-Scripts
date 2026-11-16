<?php
header("Content-Type: application/json");

require_once "db.php";
require_once "DatabaseHelper.php";
require_once "StudentGateway.php";
require_once "CourseGateway.php";
require_once "PrerequisiteGateway.php";
require_once "EnrollmentGateway.php";

function fail($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(array("error" => $message));
    exit;
}

function respond($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

try {
    // Connect using db() from db.php
    $pdo = db();

    $action = $_REQUEST["action"] ?? "";
    $table  = $_REQUEST["table"] ?? "";

    $gateways = array(
        "students"      => new StudentGateway($pdo),
        "courses"       => new CourseGateway($pdo),
        "prerequisites" => new PrerequisiteGateway($pdo),
        "enrollments"   => new EnrollmentGateway($pdo)
    );

    // 1. Populate <select> dropdown options (called by loadDropdowns() in index.html)
    if ($action === "dropdowns") {
        $students = $gateways["students"]->getAll();
        $courses  = $gateways["courses"]->getAll();

        $stuOpts = "<option value=''>Select...</option>";
        $transStuOpts = "";
        foreach ($students as $s) {
            $id = (int)$s["id"];
            $label = htmlspecialchars($s["last"] . ", " . $s["first"]);
            $stuOpts .= "<option value='$id'>$label</option>";
            $transStuOpts .= "<option value='$id'>$label</option>";
        }

        $crsOpts = "<option value=''>Select...</option>";
        foreach ($courses as $c) {
            $id = (int)$c["id"];
            $label = htmlspecialchars($c["code"] . " — " . $c["title"]);
            $crsOpts .= "<option value='$id'>$label</option>";
        }

        respond(array(
            "student_options"            => $stuOpts,
            "transcript_student_options" => $transStuOpts,
            "course_options"             => $crsOpts
        ));
    }

    // 2. Student Transcript (called by loadTranscript() in index.html)
    if ($action === "transcript") {
        $studentId = $_GET["student_id"] ?? "";
        if ($studentId === "" || !is_numeric($studentId) || !$gateways["students"]->findById((int)$studentId)) {
            fail("Student not found.", 404);
        }
        $transcript = $gateways["enrollments"]->getTranscript((int)$studentId);
        respond($transcript);
    }

    // Validate table name for CRUD operations
    if (!isset($gateways[$table])) {
        fail("Invalid table specified.", 400);
    }
    $gateway = $gateways[$table];

    // 3. List all records for Manage view (called by loadTable() in index.html)
    if ($action === "list") {
        respond(array("html" => $gateway->renderTableHtml()));
    }

    // 4. Get single record for Edit form (called by editRecord() in index.html)
    if ($action === "get") {
        $id = $_GET["id"] ?? "";
        if ($id === "" || !is_numeric($id)) {
            fail("Invalid record ID.", 400);
        }
        $record = $gateway->findById((int)$id);
        if (!$record) {
            fail("Record not found.", 404);
        }
        respond(array("data" => $record));
    }

    // 5. Create, Update, Delete (POST from forms & deleteRecord() in index.html)
    if ($action === "create") {
        $res = $gateway->create($_POST);
        if (isset($res["error"])) {
            fail($res["error"], $res["status"]);
        }
        respond(array("message" => $res["message"]));
    }

    if ($action === "update") {
        $id = $_POST["id"] ?? "";
        if ($id === "" || !is_numeric($id)) {
            fail("Invalid record ID.", 400);
        }
        $res = $gateway->update((int)$id, $_POST);
        if (isset($res["error"])) {
            fail($res["error"], $res["status"]);
        }
        respond(array("message" => $res["message"]));
    }

    if ($action === "delete") {
        $id = $_POST["id"] ?? "";
        if ($id === "" || !is_numeric($id)) {
            fail("Invalid record ID.", 400);
        }
        $res = $gateway->delete((int)$id);
        if (isset($res["error"])) {
            fail($res["error"], $res["status"]);
        }
        respond(array("message" => $res["message"]));
    }

    fail("Unknown action.", 400);

} catch (Exception $e) {
    error_log($e->getMessage());
    fail("A server error occurred while processing your request.", 500);
}
?>