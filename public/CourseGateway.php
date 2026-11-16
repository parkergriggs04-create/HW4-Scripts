<?php
class CourseGateway {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $sql = "SELECT id, code, title, credits FROM courses ORDER BY code ASC";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $sql = "SELECT id, code, title, credits FROM courses WHERE id = ?";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function validate($data, $excludeId = null) {
        $code = trim($data["code"] ?? "");
        $title = trim($data["title"] ?? "");
        $credits = $data["credits"] ?? "";

        if ($code === "" || $title === "" || $credits === "") {
            return array("status" => 400, "error" => "Code, title, and credits are required.");
        }
        if (filter_var($credits, FILTER_VALIDATE_INT) === false || (int)$credits < 1 || (int)$credits > 6) {
            return array("status" => 400, "error" => "Credits must be a whole number from 1 to 6.");
        }

        $sql = "SELECT id FROM courses WHERE code = ?";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($code));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && (int)$existing["id"] !== (int)$excludeId) {
            return array("status" => 409, "error" => "A course with this code already exists.");
        }

        return null;
    }

    public function create($data) {
        $err = $this->validate($data);
        if ($err) return $err;

        $sql = "INSERT INTO courses (code, title, credits) VALUES (?, ?, ?)";
        DatabaseHelper::runQuery($this->pdo, $sql, array(
            trim($data["code"]),
            trim($data["title"]),
            (int)$data["credits"]
        ));
        return array("status" => 200, "message" => "Course created successfully.");
    }

    public function update($id, $data) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Course record not found.");
        }
        $err = $this->validate($data, $id);
        if ($err) return $err;

        $sql = "UPDATE courses SET code = ?, title = ?, credits = ? WHERE id = ?";
        DatabaseHelper::runQuery($this->pdo, $sql, array(
            trim($data["code"]),
            trim($data["title"]),
            (int)$data["credits"],
            $id
        ));
        return array("status" => 200, "message" => "Course updated successfully.");
    }

    public function delete($id) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Course record not found.");
        }

        // Step 5 Rule 2: Cannot delete if course is in prerequisites (either as course or requirement)
        $sqlPre = "SELECT COUNT(*) AS cnt FROM course_prerequisites WHERE course_id = ? OR prereq_id = ?";
        $stmtPre = DatabaseHelper::runQuery($this->pdo, $sqlPre, array($id, $id));
        $rowPre = $stmtPre->fetch(PDO::FETCH_ASSOC);
        if ((int)$rowPre["cnt"] > 0) {
            return array("status" => 409, "error" => "This course is part of a prerequisite chain. Remove its prerequisite links first.");
        }

        // Step 5 Rule 2: Cannot delete if course has enrollments
        $sqlEnr = "SELECT COUNT(*) AS cnt FROM enrollments WHERE course_id = ?";
        $stmtEnr = DatabaseHelper::runQuery($this->pdo, $sqlEnr, array($id));
        $rowEnr = $stmtEnr->fetch(PDO::FETCH_ASSOC);
        if ((int)$rowEnr["cnt"] > 0) {
            return array("status" => 409, "error" => "This course has student enrollments. Remove its enrollments first.");
        }

        DatabaseHelper::runQuery($this->pdo, "DELETE FROM courses WHERE id = ?", array($id));
        return array("status" => 200, "message" => "Course deleted successfully.");
    }

    public function renderTableHtml() {
        $rows = $this->getAll();
        $html = "<table class='data-table'><thead><tr>" .
                "<th>Code</th><th>Title</th><th>Credits</th><th></th>" .
                "</tr></thead><tbody>";

        foreach ($rows as $r) {
            $id = (int)$r["id"];
            $html .= "<tr>" .
                "<td>" . htmlspecialchars($r["code"]) . "</td>" .
                "<td>" . htmlspecialchars($r["title"]) . "</td>" .
                "<td>" . htmlspecialchars($r["credits"]) . "</td>" .
                "<td class='actions-cell'>" .
                "<button type='button' class='action-link edit' onclick='editRecord($id)'>Edit</button> &middot; " .
                "<button type='button' class='action-link delete' onclick='deleteRecord($id)'>Delete</button>" .
                "</td></tr>";
        }
        $html .= "</tbody></table>";
        return $html;
    }
}
?>