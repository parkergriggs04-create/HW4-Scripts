<?php
class StudentGateway {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $sql = "SELECT id, first, last, email, birthdate, date_of_enrollment FROM students ORDER BY id ASC";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $sql = "SELECT id, first, last, email, birthdate, date_of_enrollment FROM students WHERE id = ?";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function validate($data, $excludeId = null) {
        $first = trim($data["first"] ?? "");
        $last = trim($data["last"] ?? "");
        $email = trim($data["email"] ?? "");
        $birth = trim($data["birthdate"] ?? "");
        $enroll = trim($data["date_of_enrollment"] ?? "");

        if ($first === "" || $last === "" || $email === "" || $birth === "" || $enroll === "") {
            return array("status" => 400, "error" => "All student fields are required.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array("status" => 400, "error" => "Please enter a valid email address.");
        }
        if (!$this->isValidDate($birth) || !$this->isValidDate($enroll)) {
            return array("status" => 400, "error" => "Birthdate and enrollment date must be valid dates (YYYY-MM-DD).");
        }

        // Check duplicate email
        $sql = "SELECT id FROM students WHERE email = ?";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($email));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && (int)$existing["id"] !== (int)$excludeId) {
            return array("status" => 409, "error" => "A student with this email already exists.");
        }

        return null;
    }

    private function isValidDate($dateStr) {
        $d = DateTime::createFromFormat("Y-m-d", $dateStr);
        return $d && $d->format("Y-m-d") === $dateStr;
    }

    public function create($data) {
        $err = $this->validate($data);
        if ($err) return $err;

        $sql = "INSERT INTO students (first, last, email, birthdate, date_of_enrollment) VALUES (?, ?, ?, ?, ?)";
        DatabaseHelper::runQuery($this->pdo, $sql, array(
            trim($data["first"]),
            trim($data["last"]),
            trim($data["email"]),
            trim($data["birthdate"]),
            trim($data["date_of_enrollment"])
        ));
        return array("status" => 200, "message" => "Student created successfully.");
    }

    public function update($id, $data) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Student record not found.");
        }
        $err = $this->validate($data, $id);
        if ($err) return $err;

        $sql = "UPDATE students SET first = ?, last = ?, email = ?, birthdate = ?, date_of_enrollment = ? WHERE id = ?";
        DatabaseHelper::runQuery($this->pdo, $sql, array(
            trim($data["first"]),
            trim($data["last"]),
            trim($data["email"]),
            trim($data["birthdate"]),
            trim($data["date_of_enrollment"]),
            $id
        ));
        return array("status" => 200, "message" => "Student updated successfully.");
    }

    public function delete($id) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Student record not found.");
        }

        // Step 5 Rule 2: A student that has enrollments cannot be deleted
        $sql = "SELECT COUNT(*) AS cnt FROM enrollments WHERE student_id = ?";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)$row["cnt"] > 0) {
            return array("status" => 409, "error" => "This student has enrollments and cannot be deleted. Remove their enrollments first.");
        }

        DatabaseHelper::runQuery($this->pdo, "DELETE FROM students WHERE id = ?", array($id));
        return array("status" => 200, "message" => "Student deleted successfully.");
    }

    public function renderTableHtml() {
        $rows = $this->getAll();
        $html = "<table class='data-table'><thead><tr>" .
                "<th>First name</th><th>Last name</th><th>Email</th><th>Birthdate</th><th>Date of enrollment</th><th></th>" .
                "</tr></thead><tbody>";

        foreach ($rows as $r) {
            $id = (int)$r["id"];
            $html .= "<tr>" .
                "<td>" . htmlspecialchars($r["first"]) . "</td>" .
                "<td>" . htmlspecialchars($r["last"]) . "</td>" .
                "<td>" . htmlspecialchars($r["email"]) . "</td>" .
                "<td>" . htmlspecialchars($r["birthdate"]) . "</td>" .
                "<td>" . htmlspecialchars($r["date_of_enrollment"]) . "</td>" .
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