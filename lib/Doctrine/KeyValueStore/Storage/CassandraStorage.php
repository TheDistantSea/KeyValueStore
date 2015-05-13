<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\KeyValueStore\Storage;

use Doctrine\KeyValueStore\Query\RangeQuery;
use Doctrine\KeyValueStore\Query\RangeQueryStorage;
use Doctrine\KeyValueStore\NotFoundException;

use Cassandra\Session;
use Cassandra\ExecutionOptions;

/**
 * Cassandra Storage Engine for KeyValueStore.
 *
 * Uses the new PHP Driver from Datastax {@link
 * https://github.com/datastax/php-driver}. Cassandra supports range queries
 * over a partition key, so we are using the RangeQueryStorage here to
 * implement more complex queries.
 */
class CassandraStorage implements Storage, RangeQueryStorage
{
    /**
     * @var \Cassandra\Session
     */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsPartialUpdates()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCompositePrimaryKeys()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function requiresCompositePrimaryKeys()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function insert($storageName, $key, array $data)
    {
        $keys = $values = array();

        foreach ($key as $name => $value) {
            $keys[] = $name;
            $values[] = $value;
        }

        foreach ($data as $name => $value) {
            $keys[] = $name;
            $values[] = $value;
        }

        $cql = "INSERT INTO " . $storageName . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', array_fill(0, count($values), '?')) . ")";
        $stmt = $this->session->prepare($cql);

        $options = new ExecutionOptions();
        $options->arguments = $values;

        $this->session->execute($stmt, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function update($storageName, $key, array $data)
    {
        $where = array();
        $set = array();

        foreach ($key as $name => $value) {
            $where[] = $name . ' = ?';
        }

        foreach ($data as $name => $value) {
            $set[] = $name . ' = ?';
        }

        $cql = "UPDATE " . $storageName . " SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
        $stmt = $this->session->prepare($cql);

        $values = array_merge(array_values($data), array_values($key));

        $options = new ExecutionOptions();
        $options->arguments = $values;

        $this->session->execute($stmt, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function delete($storageName, $key)
    {
        $where = array();

        foreach ($key as $name => $value) {
            $where[] = $name . ' = ?';
        }

        $cql = "DELETE FROM " . $storageName . " WHERE " . implode(' AND ', $where);
        $stmt = $this->session->prepare($cql);

        $options = new ExecutionOptions();
        $options->arguments = array_values($key);

        $this->session->execute($stmt, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function find($storageName, $key)
    {
        $where = array();

        foreach ($key as $name => $value) {
            $where[] = $name . ' = ?';
        }

        $cql = "SELECT * FROM " . $storageName . " WHERE " . implode(' AND ', $where);
        $stmt = $this->session->prepare($cql);

        $options = new ExecutionOptions();
        $options->arguments = array_values($key);

        $result = $this->session->execute($stmt, $options);
        $rows = iterator_to_array($result);

        if (!isset($rows[0])) {
            throw new NotFoundException();
        }

        $data = array();
        foreach ($rows[0] as $column => $value) {
            if (isset($key[$column])) {
                continue;
            }

            $data[$column] = $value;
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function executeRangeQuery(RangeQuery $query, $storageName, $key, \Closure $hydrateRow = null)
    {
        throw new \RuntimeException("TODO: Implement");
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'cassandra';
    }
}
