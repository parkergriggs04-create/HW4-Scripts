<?php
header("Content-Type: application/json");

require_once "db.php";
require_once "DatabaseHelper.php";
require_once "StudentDB.php";
require_once "CourseDB.php";
require_once "PrerequisiteDB.php";
require_once "EnrollmentDB.php";

function fail($message,$statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(array("error" => $message));
    exit;
}

function respond($payload,$statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

try {
    $pdo = db();

    $action =$_REQUEST["action"] ?? "";
    $table  =$_REQUEST["table"] ?? "";

    $studentDB = new StudentDB($pdo);
    $courseDB  = new CourseDB($pdo);
    $prereqDB  = new PrerequisiteDB($pdo);
    $enrollDB  = new EnrollmentDB($pdo);

    // 1. Transcript View
    if ($action == "transcript") {
        $studentId =$_GET["student_id"] ?? "";
        if (!is_numeric($studentId) || !$studentDB->findById((int)$studentId)) {
            fail("Student not found.", 404);
        }
        respond(array("data" => $enrollDB->getTranscript((int)$studentId)));
    }

    // 2. List All Records (Manage View & Dropdowns)
    if ($action == "list") {
        if ($table == "students") {
            respond(array("data" => $studentDB->getAll()));
        } else if ($table == "courses") {
            respond(array("data" => $courseDB->getAll()));
        } else if ($table == "prerequisites") {
            respond(array("data" => $prereqDB->getAll()));
        } else if ($table == "enrollments") {
            respond(array("data" => $enrollDB->getAll()));
        }
        fail("Invalid table.", 400);
    }

    // 3. Create Record
    if ($action == "create") {
        if ($table == "students") {
            $first  = trim($_POST["first"] ?? "");
            $last   = trim($_POST["last"] ?? "");
            $email  = trim($_POST["email"] ?? "");
            $birth  = trim($_POST["birthdate"] ?? "");
            $enroll = trim($_POST["date_of_enrollment"] ?? "");

            $err = $studentDB->validate($first, $last,$email, $birth,$enroll);
            if ($err) fail($err["error"], $err["code"]);

            $studentDB->add($first, $last,$email, $birth,$enroll);
            respond(array("message" => "Student created successfully."));
        }
        else if ($table == "courses") {
            $code    = trim($_POST["code"] ?? "");
            $title   = trim($_POST["title"] ?? "");
            $credits = trim($_POST["credits"] ?? "");

            $err = $courseDB->validate($code, $title,$credits);
            if ($err) fail($err["error"], $err["code"]);

            $courseDB->add($code, $title,$credits);
            respond(array("message" => "Course created successfully."));
        }
        else if ($table == "prerequisites") {
            $courseId =$_POST["course_id"] ?? "";
            $prereqId =$_POST["prereq_id"] ?? "";

            $err =$prereqDB->validate($courseId,$prereqId);
            if ($err) fail($err["error"], $err["code"]);

            $prereqDB->add($courseId,$prereqId);
            respond(array("message" => "Prerequisite created successfully."));
        }
        else if ($table == "enrollments") {
            $studentId =$_POST["student_id"] ?? "";
            $courseId  =$_POST["course_id"] ?? "";
            $grade     = trim($_POST["grade"] ?? "");

            $err = $enrollDB->validate($studentId, $courseId,$grade);
            if ($err) fail($err["error"], $err["code"]);

            $enrollDB->add($studentId, $courseId,$grade);
            respond(array("message" => "Enrollment created successfully."));
        }
        fail("Invalid table.", 400);
    }

    // 4. Update Record
    if ($action == "update") {
        $id =$_POST["id"] ?? "";
        if (!is_numeric($id)) fail("Invalid ID.", 400);
        $id = (int)$id;

        if ($table == "students") {
            if (!$studentDB->findById($id)) fail("Student not found.", 404);
            $first  = trim($_POST["first"] ?? "");
            $last   = trim($_POST["last"] ?? "");
            $email  = trim($_POST["email"] ?? "");
            $birth  = trim($_POST["birthdate"] ?? "");
            $enroll = trim($_POST["date_of_enrollment"] ?? "");

            $err =$studentDB->validate($first,$last, $email,$birth, $enroll,$id);
            if ($err) fail($err["error"], $err["code"]);

            $studentDB->update($id,$first, $last,$email, $birth,$enroll);
            respond(array("message" => "Student updated successfully."));
        }
        else if ($table == "courses") {
            if (!$courseDB->findById($id)) fail("Course not found.", 404);
            $code    = trim($_POST["code"] ?? "");
            $title   = trim($_POST["title"] ?? "");
            $credits = trim($_POST["credits"] ?? "");

            $err =$courseDB->validate($code,$title, $credits,$id);
            if ($err) fail($err["error"], $err["code"]);

            $courseDB->update($id,$code, $title,$credits);
            respond(array("message" => "Course updated successfully."));
        }
        else if ($table == "prerequisites") {
            if (!$prereqDB->findById($id)) fail("Prerequisite not found.", 404);
            $courseId =$_POST["course_id"] ?? "";
            $prereqId =$_POST["prereq_id"] ?? "";

            $err = $prereqDB->validate($courseId, $prereqId,$id);
            if ($err) fail($err["error"], $err["code"]);

            $prereqDB->update($id, $courseId,$prereqId);
            respond(array("message" => "Prerequisite updated successfully."));
        }
        else if ($table == "enrollments") {
            if (!$enrollDB->findById($id)) fail("Enrollment not found.", 404);
            $studentId =$_POST["student_id"] ?? "";
            $courseId  =$_POST["course_id"] ?? "";
            $grade     = trim($_POST["grade"] ?? "");

            $err =$enrollDB->validate($studentId,$courseId, $grade,$id);
            if ($err) fail($err["error"], $err["code"]);

            $enrollDB->update($id,$studentId, $courseId,$grade);
            respond(array("message" => "Enrollment updated successfully."));
        }
        fail("Invalid table.", 400);
    }

    // 5. Delete Record
    if ($action == "delete") {
        $id =$_POST["id"] ?? "";
        if (!is_numeric($id)) fail("Invalid ID.", 400);
        $id = (int)$id;

        if ($table == "students") {
            if (!$studentDB->findById($id)) fail("Student not found.", 404);
            $delErr = $studentDB->delete($id);
            if ($delErr) fail($delErr, 409);
            respond(array("message" => "Student deleted successfully."));
        }
        else if ($table == "courses") {
            if (!$courseDB->findById($id)) fail("Course not found.", 404);
            $delErr = $courseDB->delete($id);
            if ($delErr) fail($delErr, 409);
            respond(array("message" => "Course deleted successfully."));
        }
        else if ($table == "prerequisites") {
            if (!$prereqDB->findById($id)) fail("Prerequisite not found.", 404);
            $prereqDB->delete($id);
            respond(array("message" => "Prerequisite deleted successfully."));
        }
        else if ($table == "enrollments") {
            if (!$enrollDB->findById($id)) fail("Enrollment not found.", 404);
            $enrollDB->delete($id);
            respond(array("message" => "Enrollment deleted successfully."));
        }
        fail("Invalid table.", 400);
    }

    fail("Unknown action.", 400);

} catch (Exception $e) {
    error_log($e->getMessage());
    fail("Database error occurred.", 500);
}
?>