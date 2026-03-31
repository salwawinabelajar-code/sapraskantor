<?php
session_start();

class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "sapraskantor";
    public $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
        
        if ($this->conn->connect_error) {
            die("Koneksi gagal: " . $this->conn->connect_error);
        }
    }

    public function escape_string($string) {
        return $this->conn->real_escape_string($string);
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }
}

// Fungsi helper
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function generateInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    return substr($initials, 0, 2);
}

function getStatusColor($status) {
    switch($status) {
        case 'Menunggu': return 'warning';
        case 'Diproses': return 'info';
        case 'Selesai': return 'success';
        case 'Ditolak': return 'danger';
        default: return 'secondary';
    }
}

function getPriorityColor($priority) {
    switch($priority) {
        case 'Rendah': return 'success';
        case 'Sedang': return 'warning';
        case 'Tinggi': return 'danger';
        default: return 'secondary';
    }
}
?>