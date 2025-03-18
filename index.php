<?php
require 'QueryBuilder.php';

// Veritabanı bağlantısı
$dsn = 'mysql:host=localhost;dbname=querybuilder;charset=utf8mb4';
$username = 'root';
$password = '';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantısı başarısız: " . $e->getMessage());
}

// QueryBuilder örneği oluşturma
$queryBuilder = new QueryBuilder($pdo);

// Yeni kullanıcı ekleme
$lastInsertId = $queryBuilder->insert('users', [
    'name' => 'Elfesya ESEN',
    'email' => 'elfesyaesen@gmail.com',
]);

echo "Eklenen Kullanıcı ID: $lastInsertId<hr/>";

// Kullanıcı bilgilerini güncelleme
$affectedRows = $queryBuilder->update('users', [
    'email' => 'elfesyaesen@example.com',
], 'id', 1);

echo "Güncellenen Kullanıcı Sayısı: $affectedRows<hr/>";

// Yeni gönderi ekleme
$lastInsertId = $queryBuilder->insert('posts', [
    'user_id' => $lastInsertId,
    'title' => 'İlk Gönderi',
    'content' => 'Bu benim ilk gönderim.',
]);

echo "Eklenen Gönderi ID: $lastInsertId<hr/>";

// Gönderi silme
$affectedRows = $queryBuilder->delete('posts')
    ->where('id', Operator::EQUALS, 1)
    ->execute()
    ->rowCount();

echo "Gönderi silme : $affectedRows<hr/>";

// Yeni yorum ekleme
$lastInsertId = $queryBuilder->insert('comments', [
    'post_id' => $lastInsertId, // İlk gönderinin ID'si
    'user_id' => 1,
    'comment' => 'Harika bir gönderi!',
]);

echo "Eklenen Yorum ID: $lastInsertId<hr/>";

// Tüm gönderileri sorgulama
$posts = $queryBuilder->select()->from('posts')->get();

echo "Tüm Gönderiler:<hr/>";
print_r($posts);

// Kullanıcıya ait gönderileri sorgulama
$userPosts = $queryBuilder->select()
    ->from('posts')
    ->where('user_id', Operator::EQUALS, 2)
    ->get();

echo "Elfesya ESEN'ın Gönderileri:<hr/>";
print_r($userPosts);


// Gönderiye ait yorumları sorgulama
$comments = $queryBuilder->select(['comments.comment', 'users.name'])
    ->from('comments')
    ->innerJoin('users', 'comments.user_id', Operator::EQUALS, 'users.id')
    ->where('comments.post_id', Operator::EQUALS, 5)
    ->get();

echo "Gönderiye Ait Yorumlar:<hr/>";
print_r($comments);

// Kullanıcı silme
$affectedRows = $queryBuilder->delete('users')
    ->where('id', Operator::EQUALS, 1)
    ->execute()
    ->rowCount();

echo "Silinen Kullanıcı Sayısı: $affectedRows<hr/>";


// Sayfalama örneği
$page = 1; // 1. sayfa
$perPage = 10; // Her sayfada 10 kayıt

$posts = $queryBuilder->select()
    ->from('posts')
    ->paginate($page, $perPage)
    ->get();

echo "Sayfa 1 Gönderileri:<hr/>";
print_r($posts);


// toplu işlem yapma
try {
    $queryBuilder->transaction(function (QueryBuilder $qb) {
        // Yeni kullanıcı ekleme
        $userId = $qb->insert('users', [
            'name' => 'test Demir',
            'email' => 'mehmet@example.com',
        ]);

        // Yeni gönderi ekleme
        $postId = $qb->insert('posts', [
            'user_id' => $userId,
            'title' => 'Transaction Örneği',
            'content' => 'Bu bir transaction örneğidir.',
        ]);

        // Yeni yorum ekleme
        $qb->insert('comments', [
            'post_id' => $postId,
            'user_id' => $userId,
            'comment' => 'Transaction başarılı!',
        ]);

        echo "Transaction başarıyla tamamlandı.<hr/>";
    });
} catch (Throwable $e) {
    echo "Transaction sırasında hata oluştu: " . $e->getMessage() . "<hr/>";
}

// Alt sorgu: Yorum yapmış kullanıcıların ID'lerini bul
$subQuery = (new QueryBuilder($pdo))
    ->select('user_id')
    ->from('comments');

// Ana sorgu: Alt sorgudaki kullanıcıları getir
$usersWithComments = $queryBuilder->select()
    ->from('users')
    ->where('id', Operator::IN, $subQuery)
    ->get();

echo "Yorum Yapmış Kullanıcılar:<hr/>";
print_r($usersWithComments);
