# QueryBuilder

`QueryBuilder`, PHP'de veritabanı sorgularını kolayca oluşturmak ve yönetmek için kullanılan bir sınıftır. Bu sınıf, `PDO` tabanlıdır ve temel CRUD işlemlerini, JOIN'leri, alt sorguları, transaction yönetimini ve daha fazlasını destekler.

## Kurulum

1. `QueryBuilder` sınıfını projenize dahil edin.
2. Veritabanı bağlantısı için `PDO` nesnesi oluşturun.
3. `QueryBuilder` örneği oluşturun.

```php
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
```

## Temel Kullanım Örnekleri

### Veri Ekleme (INSERT)

```php
$lastInsertId = $queryBuilder->insert('users', [
    'name' => 'Elfesya ESEN',
    'email' => 'elfesyaesen@gmail.com',
]);

echo "Eklenen Kullanıcı ID: $lastInsertId";
```

### Veri Güncelleme (UPDATE)

```php
$affectedRows = $queryBuilder->update('users', [
    'email' => 'elfesyaesen@example.com',
], 'id', 1);

echo "Güncellenen Kullanıcı Sayısı: $affectedRows";
```

### Veri Silme (DELETE)

```php
$affectedRows = $queryBuilder->delete('users')
    ->where('id', Operator::EQUALS, 1)
    ->execute()
    ->rowCount();

echo "Silinen Kullanıcı Sayısı: $affectedRows";
```

### Veri Sorgulama (SELECT)

```php
$users = $queryBuilder->select()->from('users')->get();
print_r($users);
```

### JOIN İşlemleri

```php
$posts = $queryBuilder->select(['posts.title', 'users.name'])
    ->from('posts')
    ->innerJoin('users', 'posts.user_id', Operator::EQUALS, 'users.id')
    ->get();

print_r($posts);
```

### Alt Sorgu (Subquery)

```php
$subQuery = (new QueryBuilder($pdo))
    ->select('user_id')
    ->from('comments');

$usersWithComments = $queryBuilder->select()
    ->from('users')
    ->where('id', Operator::IN, $subQuery)
    ->get();

print_r($usersWithComments);
```

### Transaction Yönetimi

```php
try {
    $queryBuilder->transaction(function (QueryBuilder $qb) {
        $userId = $qb->insert('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $qb->insert('posts', [
            'user_id' => $userId,
            'title' => 'Test Post',
            'content' => 'This is a test post.',
        ]);

        echo "Transaction başarıyla tamamlandı.";
    });
} catch (Throwable $e) {
    echo "Transaction sırasında hata oluştu: " . $e->getMessage();
}
```

### Sayfalama (Pagination)

```php
$page = 1; // 1. sayfa
$perPage = 10; // Her sayfada 10 kayıt

$posts = $queryBuilder->select()
    ->from('posts')
    ->paginate($page, $perPage)
    ->get();

print_r($posts);
```

## Operatörler

`QueryBuilder` sınıfı, aşağıdaki operatörleri destekler:

```php
enum Operator: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN_OR_EQUAL = '<=';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case BETWEEN = 'BETWEEN';
    case NOT_BETWEEN = 'NOT BETWEEN';
    case IS_NULL = 'IS NULL';
    case IS_NOT_NULL = 'IS NOT NULL';
}
```

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Daha fazla bilgi için `LICENSE` dosyasına bakın.

---

Bu içeriği GitHub `README.md` dosyasına ekleyebilirsiniz. Bu, kullanıcıların `QueryBuilder` sınıfını nasıl kullanacaklarını anlamalarına yardımcı olacaktır. 🚀
