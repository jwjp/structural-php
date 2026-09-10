<?php

namespace App\Models\Sample;

use App\system\Database;
use App\system\QueryBuilder;
use Exception;
use PDO;
use RuntimeException;

class SampleModel extends Database {
    /**
     * @param string $message
     * @return false|int
     * @throws Exception
     */
    public function createFoo(string $message): false|int {
        $query = 'INSERT INTO foo SET message = :message';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':message', $message);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->rowCount();
        }

        return $result;
    }

    /**
     * @param int $id
     * @return false|array
     * @throws Exception
     */
    public function readFooById(int $id): false|array {
        $query = 'SELECT * FROM foo WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $result;
    }

    /**
     * @return false|array
     */
    public function readFooAll(): false|array {
        $query = 'SELECT * FROM foo WHERE deleted_at IS NULL ORDER BY id DESC';
        $stmt = $this->pdo->prepare($query);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $result;
    }

    /**
     * @param string $message
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function updateFooById(string $message, int $id): false|int {
        $query = 'UPDATE foo SET message = :message, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $execute = $stmt->execute([
            ':message' => $message,
            ':id' => $id
        ]);

        return $execute ? $stmt->rowCount() : false;
    }

    /**
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function deleteFooById(int $id): false|int {
        $query = 'UPDATE foo SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->rowCount();
        }

        return $result;
    }

    /**
     * @param string $comment
     * @param int $fooId
     * @return false|int
     * @throws Exception
     */
    public function createBarWithFooId(string $comment, int $fooId): false|int {
        // A soft-deleted or missing parent cannot receive new comments.
        $query = 'INSERT INTO bar (comment, foo_id) SELECT :comment, id FROM foo WHERE id = :foo_id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':comment', $comment);
        $stmt->bindParam(':foo_id', $fooId, PDO::PARAM_INT);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->rowCount();
        }

        return $result;
    }

    /**
     * @param int $id
     * @return false|array
     * @throws Exception
     */
    public function readBarById(int $id): false|array {
        $query = 'SELECT * FROM bar WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $result;
    }

    /**
     * @return false|array
     */
    public function readBarAll(): false|array {
        $query = 'SELECT * FROM bar WHERE deleted_at IS NULL ORDER BY id DESC';
        $stmt = $this->pdo->prepare($query);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $result;
    }

    /**
     * @param string $comment
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function updateBarById(string $comment, int $id): false|int {
        $query = 'UPDATE bar SET comment = :comment, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $execute = $stmt->execute([
            ':comment' => $comment,
            ':id' => $id
        ]);

        return $execute ? $stmt->rowCount() : false;
    }

    /**
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function deleteBarById(int $id): false|int {
        $query = 'UPDATE bar SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->rowCount();
        }

        return $result;
    }

    /**
     * @param int $fooId
     * @return false|array
     * @throws Exception
     */
    public function readFooBarByFooIdWithQB(int $fooId): false|array {
        $qb = $this->queryBuilder();
        return $qb->table('foo f')
            ->join('bar b', 'INNER', 'b.foo_id', '=', 'f.id')
            ->select('b.*')
            ->where('b.foo_id', '=', $fooId)
            ->where('b.deleted_at', 'IS', null)
            ->where('f.deleted_at', 'IS', null)
            ->orderBy('b.id', 'DESC')
            ->exec();
    }

    /**
     * @param string $message
     * @param string $comment
     * @return bool
     * @throws Exception
     */
    public function createFooBarWithQB(string $message, string $comment): bool {
        $qb = $this->queryBuilder();
        return $qb->transaction(function (QueryBuilder $qb) use ($message, $comment): bool {
            $fooId = $qb->table('foo')->insert(['message' => $message])->exec();
            if ($fooId === false) {
                throw new RuntimeException('Failed to create foo.');
            }

            $barId = $qb->table('bar')->insert(['foo_id' => $fooId, 'comment' => $comment])->exec();
            if ($barId === false) {
                throw new RuntimeException('Failed to create bar.');
            }

            return true;
        });
    }

    /**
     * @param string $message
     * @return array|false|int|string
     * @throws Exception
     */
    public function createFooWithQB(string $message): false|array|int|string {
        $qb = $this->queryBuilder();
        return $qb->table('foo')
            ->insert([
                'message' => $message
            ])
            ->exec();
    }

    /**
     * @param int $fooId
     * @return array|false|int|string
     * @throws Exception
     */
    public function deleteFooByIdWithQB(int $fooId): false|array|int|string {
        $qb = $this->queryBuilder();
        return $qb->table('foo')
            ->update([
                'deleted_at' => date('Y-m-d H:i:s')
            ])
            ->where('id', '=', $fooId)
            ->where('deleted_at', 'IS', null)
            ->exec();
    }
}
