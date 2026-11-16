<?php
class PrerequisiteGateway {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $sql = "SELECT cp.id, cp.course_id, cp.prereq_id,
                       c1.code AS course_code, c2.code AS prereq_code
                FROM course_prerequisites cp
                INNER JOIN courses c1 ON cp.course_id = c1.id
                INNER JOIN courses c2 ON cp.prereq_id = c2.id
                ORDER BY c1.code ASC, c2.code ASC";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $sql = "SELECT id, course_id, prereq_id FROM course_prerequisites WHERE id = ?";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql, array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Checks if $startCourseId already depends on $targetCourseId (directly or indirectly)
    private function createsCycle($courseId, $prereqId, $excludeId = null) {
        if ((int)$courseId === (int)$prereqId) {
            return true;
        }

        // Load all existing prerequisite pairs (excluding the row being edited, if any)
        $sql = "SELECT id, course_id, prereq_id FROM course_prerequisites";
        $stmt = DatabaseHelper::runQuery($this->pdo, $sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $adj = array();
        foreach ($rows as $r) {
            if ($excludeId !== null && (int)$r["id"] === (int)$excludeId) {
                continue;
            }
            $c = (int)$r["course_id"];
            $p = (int)$r["prereq_id"];
            if (!isset($adj[$c])) {
                $adj[$c] = array();
            }
            $adj[$c][] = $p;
        }

        // BFS starting from $prereqId: if we can reach $courseId, adding courseId -> prereqId forms a loop
        $queue = array((int)$prereqId);
        $visited = array();

        while (count($queue) > 0) {
            $curr = array_shift($queue);
            if ($curr === (int)$courseId) {
                return true;
            }
            $visited[$curr] = true;

            if (isset($adj[$curr])) {
                foreach ($adj[$curr] as $next) {
                    if (!isset($visited[$next])) {
                        $queue[] = $next;
                    }
                }
            }
        }
        return false;
    }

    private function validate($data, $excludeId = null) {
        $courseId = $data["course_id"] ?? "";
        $prereqId = $data["prereq_id"] ?? "";

        if ($courseId === "" || $prereqId === "") {
            return array("status" => 400, "error" => "Both course and prerequisite are required.");
        }

        // Verify both courses exist
        $stmt1 = DatabaseHelper::runQuery($this->pdo, "SELECT id FROM courses WHERE id = ?", array($courseId));
        $stmt2 = DatabaseHelper::runQuery($this->pdo, "SELECT id FROM courses WHERE id = ?", array($prereqId));
        if (!$stmt1->fetch() || !$stmt2->fetch()) {
            return array("status" => 400, "error" => "Selected course does not exist.");
        }

        if ((int)$courseId === (int)$prereqId) {
            return array("status" => 409, "error" => "A course cannot be its own prerequisite.");
        }

        // Check duplicate prerequisite link
        $sqlDup = "SELECT id FROM course_prerequisites WHERE course_id = ? AND prereq_id = ?";
        $stmtDup = DatabaseHelper::runQuery($this->pdo, $sqlDup, array($courseId, $prereqId));
        $existing = $stmtDup->fetch(PDO::FETCH_ASSOC);
        if ($existing && (int)$existing["id"] !== (int)$excludeId) {
            return array("status" => 409, "error" => "This prerequisite already exists.");
        }

        // Step 5 Rule 1: Circular prerequisite check
        if ($this->createsCycle($courseId, $prereqId, $excludeId)) {
            return array("status" => 409, "error" => "Circular prerequisite rejected: this would create a prerequisite loop.");
        }

        return null;
    }

    public function create($data) {
        $err = $this->validate($data);
        if ($err) return $err;

        $sql = "INSERT INTO course_prerequisites (course_id, prereq_id) VALUES (?, ?)";
        DatabaseHelper::runQuery($this->pdo, $sql, array((int)$data["course_id"], (int)$data["prereq_id"]));
        return array("status" => 200, "message" => "Prerequisite created successfully.");
    }

    public function update($id, $data) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Prerequisite record not found.");
        }
        $err = $this->validate($data, $id);
        if ($err) return $err;

        $sql = "UPDATE course_prerequisites SET course_id = ?, prereq_id = ? WHERE id = ?";
        DatabaseHelper::runQuery($this->pdo, $sql, array((int)$data["course_id"], (int)$data["prereq_id"], $id));
        return array("status" => 200, "message" => "Prerequisite updated successfully.");
    }

    public function delete($id) {
        if (!$this->findById($id)) {
            return array("status" => 404, "error" => "Prerequisite record not found.");
        }
        DatabaseHelper::runQuery($this->pdo, "DELETE FROM course_prerequisites WHERE id = ?", array($id));
        return array("status" => 200, "message" => "Prerequisite deleted successfully.");
    }

    public function renderTableHtml() {
        $rows = $this->getAll();
        $html = "<table class='data-table'><thead><tr>" .
                "<th>Course</th><th>Requires</th><th></th>" .
                "</tr></thead><tbody>";

        foreach ($rows as $r) {
            $id = (int)$r["id"];
            $html .= "<tr>" .
                "<td>" . htmlspecialchars($r["course_code"]) . "</td>" .
                "<td>" . htmlspecialchars($r["prereq_code"]) . "</td>" .
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