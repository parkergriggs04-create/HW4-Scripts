<?php
class EnrollmentGateway {
    private $pdo;
    private $validGrades = array("A", "A-", "B+", "B", "B-", "C+", "C", "C-", "D", "F");
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    public function getAll() {
        $sql = "SELECT e.id, e.student_id, e.course_id, e.grade,
                       s.first, s.last, c.code AS course_code
                FROM enrollments e
                INNER JOIN students s ON e.student_id = s.id
                INNER JOIN courses c ON e.course_id = c.id
                ORDER BY s.last ASC, s.first ASC, c.code ASC";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function findById($id) {
        $sql = "SELECT id, student_id, course_id, grade FROM enrollments WHERE id = ?";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    private function validate($data, $excludeId = null) {
        $studentId = $data["student_id"] ?? "";
        $courseId = $data["course_id"] ?? "";
        $grade = trim($data["grade"] ?? "");

        if ($studentId === "" || $courseId === "" || $grade === "") {
            return array("status" => 400, "error" => "Student, course, and grade are required.");
        }
        if (!in_array($grade, $this->validGrades, true)) {
            return array("status" => 400, "error" => "Invalid grade selected.");
        }

        // Check referenced student and course exist
        $stmtStu = DatabaseHelper::runQuery($this->pdo, "SELECT id FROM students WHERE id = ?", array($studentId));
        $stmtCrs = DatabaseHelper::runQuery($this->pdo, "SELECT id FROM courses WHERE id = ?", array($courseId));
        if (!$stmtStu->fetch() || !$stmtCrs->fetch()) {
            return array("status" => 400, "error" => "Selected student or course does not exist.");
        }

        // A student can take a given course only once
        $sqlDup = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?";
        $stmtDup = DatabaseHelper::runQuery($this->pdo, $sqlDup, array($studentId, $courseId));
        $existing = $stmtDup->fetch(PDO::FETCH_ASSOC);
        if ($existing && (int)$existing["id"] !== (int)$excludeId) {
            return array("status" => 409, "error" => "This student is already enrolled in this course.");
        }

        return null;
    }
    public function create($data) {
        $err = $this->validate($data);
        if ($err) return $err;

        $sql = "INSERT INTO enrollments (student_id, course_id, grade) VALUES (?, ?, ?)";
        DatabaseHelper::runQuery($this->pdo, $sql, array(
            (int)$data["student_id"],
            (int)$data["course_id"],
            trim($data["grade"])
        ));
        return array("status" => 200, "message" => "Enrollment created successfully.");
    }
    public function update($id, $data) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Enrollment record not found.");
        }
        $err = $this->validate($data, $id);
        if ($err) return $err;

        $sql = "UPDATE enrollments SET student_id = ?, course_id = ?, grade = ? WHERE id = ?";
        DatabaseHelper::runQuery($this->pdo, $sql, array(
            (int)$data["student_id"],
            (int)$data["course_id"],
            trim($data["grade"]),
            $id
        ));
        return array("status" => 200, "message" => "Enrollment updated successfully.");
    }
    public function delete($id) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Enrollment record not found.");
        }
        DatabaseHelper::runQuery($this->pdo, "DELETE FROM enrollments WHERE id = ?", array($id));
        return array("status" => 200, "message" => "Enrollment deleted successfully.");
    }
    public function renderTableHtml() {
        $rows = $this->getAll();
        $html = "<table class='data-table'><thead><tr>" .
                "<th>Student</th><th>Course</th><th>Grade</th><th></th>" .
                "</tr></thead><tbody>";

        foreach ($rows as $r) {
            $id = (int)$r["id"];
            $studentName = $r["last"] . ", " . $r["first"];
            $html .= "<tr>" .
                "<td>" . htmlspecialchars($studentName) . "</td>" .
                "<td>" . htmlspecialchars($r["course_code"]) . "</td>" .
                "<td>" . htmlspecialchars($r["grade"]) . "</td>" .
                "<td class='actions-cell'>" .
                "<button type='button' class='action-link edit' onclick='editRecord($id)'>Edit</button> &middot; " .
                "<button type='button' class='action-link delete' onclick='deleteRecord($id)'>Delete</button>" .
                "</td></tr>";
        }
        $html .= "</tbody></table>";
        return $html;
    }
    public function getTranscript($studentId) {
        $sql = "SELECT c.code, c.title, c.credits, e.grade
                FROM enrollments e
                INNER JOIN courses c ON e.course_id = c.id
                WHERE e.student_id = ?
                ORDER BY c.code ASC";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($studentId));
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCredits = 0;
        $html = "<table class='data-table'><thead><tr>" .
                "<th>Code</th><th>Title</th><th>Credits</th><th>Grade</th>" .
                "</tr></thead><tbody>";
        foreach ($courses as $c) {
            $totalCredits += (int)$c["credits"];
            $html .= "<tr>" .
                "<td>" . htmlspecialchars($c["code"]) . "</td>" .
                "<td>" . htmlspecialchars($c["title"]) . "</td>" .
                "<td>" . htmlspecialchars($c["credits"]) . "</td>" .
                "<td>" . htmlspecialchars($c["grade"]) . "</td>" .
                "</tr>";
        }
        $html .= "</tbody></table>";

        return array(
            "html" => $html,
            "total_credits" => $totalCredits
        );
    }
}
?>