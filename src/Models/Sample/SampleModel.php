<?php

namespace App\Models\Sample;

use App\core\Database;
use Exception;
use PDO;

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
        $query = 'UPDATE foo SET message = :message, updated_at = NOW() WHERE id = :id';
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
        $query = 'UPDATE foo SET deleted_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id);

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
        $query = 'INSERT INTO bar SET comment = :comment, foo_id = :foo_id';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':comment', $comment);
        $stmt->bindParam(':foo_id', $fooId);

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
        $stmt->bindParam(':id', $id);

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
        $query = 'UPDATE bar SET comment = :comment, updated_at = NOW() WHERE id = :id';
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
        $query = 'UPDATE bar SET deleted_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':id', $id);

        $result = false;
        if ($stmt->execute()) {
            $result = $stmt->rowCount();
        }

        return $result;
    }

    /**
     * @param int $fooId
     * @return array
     * @throws Exception
     */
    public function readFooBarByFooIdWithQB(int $fooId): array {
        $qb = $this->queryBuilder();
        return $qb->table('foo f')
            ->join('bar b', 'INNER', 'b.foo_id', '=', 'f.id')
            ->select('b.*')
            ->where('b.foo_id', '=', [$fooId, 'EMAIL'])
            ->where('b.deleted_at', 'IS', 'NULL')
            ->where('f.deleted_at', 'IS', 'NULL')
            ->orderBy('b.id', 'DESC')
            // ->exec();
            ->debug();
    }

    /**
     * @param string $convertedMessage
     * @param string $convertedComment
     * @return bool
     * @throws Exception
     */
    public function createFooBarWithQB(string $convertedMessage, string $convertedComment): bool {
        $qb = $this->queryBuilder();
        $qb->beginTransaction();

        $fooId = $qb->table('foo')
            ->insert([
                'message' => $convertedMessage
            ])
            ->exec();
        $barId = $qb->table('bar')
            ->insert([
                'foo_id' => $fooId,
                'comment' => $convertedComment
            ])
            ->exec();

        return $qb->commit();
    }

    /**
     * @param string $convertedMessage
     * @return array|false|int|string
     * @throws Exception
     */
    public function createFooWithQB(string $convertedMessage): false|array|int|string {
        $qb = $this->queryBuilder();
        return $qb->table('foo')
            ->insert([
                'message' => [$convertedMessage, 'ALPHABET']
            ])
            ->exec();
    }

    /**
     * @param string $fooId
     * @return array|false|int|string
     * @throws Exception
     */
    public function deleteFooByIdWithQB(string $fooId): false|array|int|string {
        $qb = $this->queryBuilder();
        return $qb->table('foo')
            ->update([
                'deleted_at' => date('Y-m-d H:i:s')
            ])
            ->where('id', '=', $fooId)
            ->debug();
//        return $qb->table('foo')
//            ->delete()
//            ->where('id', '=', $fooId)
//            ->exec();
    }
}