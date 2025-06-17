<?php

namespace App\Models;

use PDO;
use DateTime;

class Report extends Model {
    protected string $table = 'reports';
    public ?int $id = null;
    public string $report_type; // e.g., 'daily_summary', 'monthly_gerant_performance'
    public ?int $generated_by_user_id = null;
    public string $generation_date; // Timestamp
    public ?string $file_path = null; // Path to the generated PDF/Excel file
    public ?string $criteria = null; // JSONB, store as string, handle encode/decode in app
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Get the User who generated this report, if any.
     * @return User|null
     */
    public function generatedByUser(): ?User {
        if ($this->generated_by_user_id === null) {
            return null;
        }
        return User::find($this->generated_by_user_id);
    }

    /**
     * Get criteria as an associative array.
     * @return array|null
     */
    public function getCriteriaArray(): ?array {
        if ($this->criteria === null) {
            return null;
        }
        return json_decode($this->criteria, true);
    }

    /**
     * Set criteria from an associative array.
     * @param array $criteriaArray
     */
    public function setCriteriaArray(array $criteriaArray): void {
        $this->criteria = json_encode($criteriaArray);
    }

    /**
     * Log a new report generation.
     * @param string $reportType
     * @param string|null $filePath
     * @param array|null $criteria
     * @param int|null $generatedByUserId
     * @return int|false The ID of the newly created report log or false on failure.
     */
    public static function logReport(string $reportType, ?string $filePath = null, ?array $criteria = null, ?int $generatedByUserId = null): int|false {
        $data = [
            'report_type' => $reportType,
            'generated_by_user_id' => $generatedByUserId,
            'generation_date' => (new DateTime())->format('Y-m-d H:i:s'),
            'file_path' => $filePath,
            'criteria' => $criteria ? json_encode($criteria) : null,
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ];
        return self::create($data);
    }

    /**
     * Get recent reports.
     * @param int $limit
     * @return array
     */
    public static function getRecentReports(int $limit = 20): array {
        $sql = "SELECT r.*, u.username as generated_by_username
                FROM reports r
                LEFT JOIN users u ON r.generated_by_user_id = u.id
                ORDER BY r.generation_date DESC
                LIMIT :limit";

        $stmt = self::db()->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }
}
