<?php
    namespace core\Database;

    use PDO;
    use PDOException;
    use core\Exceptions\Database\QueryException;

    class Statements
    {
        private PDO $connection;

        public const RETURN_ARRAY = 'array';
        public const RETURN_OBJECT = 'object';
        public const RETURN_BOTH = 'both';

        public function __construct(PDO $connection)
        {
            $this->connection = $connection;
        }

        private function getFetchMode(string $mode): int
        {
            return match (strtolower($mode)) {
                self::RETURN_OBJECT => PDO::FETCH_OBJ,
                self::RETURN_BOTH   => PDO::FETCH_BOTH,
                default             => PDO::FETCH_ASSOC,
            };
        }

        /**
         * Lanza una excepción específica de QueryException.
         */
        private function handleException(PDOException $e, string $method, string $query, array $params = []): never
        {
            // Opcional: log a archivo o monitoreo
            error_log("DB Error in {$method}: " . $e->getMessage() . " | Query: {$query}");

            throw new QueryException("Database query failed in {$method}");
        }

        // 🔹 Selecciona un solo registro
        public function select_one(string $query, array $params = [], string $returnType = self::RETURN_ARRAY): array|object|null
        {
            try {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($params);
                $result = $stmt->fetch($this->getFetchMode($returnType));

                return $result === false ? null : $result;
            } catch (PDOException $e) {
                $this->handleException($e, __FUNCTION__, $query, $params);
            }
        }

        // 🔹 Selecciona múltiples registros
        public function select_all(string $query, array $params = [], string $returnType = self::RETURN_ARRAY): array
        {
            try {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($params);
                return $stmt->fetchAll($this->getFetchMode($returnType)) ?: [];
            } catch (PDOException $e) {
                $this->handleException($e, __FUNCTION__, $query, $params);
            }
        }

        // 🔹 Inserta un registro y devuelve el ID
        public function insert(string $query, array $values = []): ?string
        {
            try {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($values);
                return $this->connection->lastInsertId();
            } catch (PDOException $e) {
                $this->handleException($e, __FUNCTION__, $query, $values);
            }
        }

        // 🔹 Actualiza registros
        public function update(string $query, array $params = []): int
        {
            try {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($params);
                return $stmt->rowCount();
            } catch (PDOException $e) {
                $this->handleException($e, __FUNCTION__, $query, $params);
            }
        }

        // 🔹 Elimina registros
        public function delete(string $query, array $params = []): int
        {
            try {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($params);
                return $stmt->rowCount();
            } catch (PDOException $e) {
                $this->handleException($e, __FUNCTION__, $query, $params);
            }
        }

        // 🔹 Ejecuta una consulta sin retorno (CREATE, DROP, ALTER)
        public function execute(string $query, array $params = []): bool
        {
            try {
                $stmt = $this->connection->prepare($query);
                return $stmt->execute($params);
            } catch (PDOException $e) {
                $this->handleException($e, __FUNCTION__, $query, $params);
            }
        }

        public function loadData(string $filePath, string $extension): void
        {
            switch (strtolower($extension)) {
                case 'sql':
                    $this->loadSqlFile($filePath);
                break;

                case 'json':
                    $this->loadJsonFile($filePath);
                break;

                case 'csv':
                case 'txt':
                    $this->loadCsvFile($filePath, $options['table'] ?? null, $options['delimiter'] ?? ',', $options['skipHeader'] ?? true);
                break;
                default:
                    throw new QueryException("Unsupported file format: .{$extension}");
            }
        }

        private function loadSqlFile(string $filePath): void
        {
            $sql = file_get_contents($filePath);

            // Divide las sentencias por ';' si hay varias
            $queries = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($queries as $query) {
                if ($query !== '') {
                    $this->connection->exec($query);
                }
            }
        }

        private function loadJsonFile(string $filePath): void
        {
            $data = json_decode(file_get_contents($filePath), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QueryException("Invalid JSON format in {$filePath}");
            }

            foreach ($data as $item) {
                if (!isset($item['query'], $item['params'])) {
                    throw new QueryException("Invalid JSON data format. Each item must have 'query' and 'params'");
                }

                $stmt = $this->connection->prepare($item['query']);
                $stmt->execute($item['params']);
            }
        }


        /**
     * Carga datos desde un archivo CSV.
     */
    private function loadCsvFile(string $filePath, ?string $table, string $delimiter = ',', bool $skipHeader = true, ?array $columns = null): void
    {
        if (!$table) {
            throw new QueryException("Table name is required for CSV import.");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new QueryException("Unable to open file: {$filePath}");
        }

        $header = $columns;
        $rowCount = 0;

        $this->connection->beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($rowCount === 0 && $skipHeader && !$columns) {
                    $header = $row;
                    $rowCount++;
                    continue;
                }

                if (!$header) {
                    throw new QueryException("Missing CSV header or column names for file: {$filePath}");
                }

                $columnsList = implode(',', $header);
                $placeholders = implode(',', array_fill(0, count($header), '?'));
                $query = "INSERT INTO {$table} ({$columnsList}) VALUES ({$placeholders})";

                $stmt = $this->connection->prepare($query);
                $stmt->execute($row);
                $rowCount++;
            }

            $this->connection->commit();
        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw new QueryException("Unable to open file: {$filePath}");
        } finally {
            fclose($handle);
        }
    }


        // 🔹 Manejo de transacciones
        public function beginTransaction(): void
        {
            $this->connection->beginTransaction();
        }

        public function commit(): void
        {
            $this->connection->commit();
        }

        public function rollBack(): void
        {
            $this->connection->rollBack();
        }

        // 🔹 Retorna el objeto PDO subyacente
        public function getConnection(): PDO
        {
            return $this->connection;
        }

        public function load_data2($file, $table){
            // Validación de $file y $table (por ejemplo, solo permitir letras y números en el nombre de la tabla)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                return "El nombre de la tabla no es válido";
            }
            // Escapamos la ruta del archivo
            //$file = escapeshellarg($file); // Aseguramos que la ruta sea segura
            // Preparamos la consulta
            $query = "LOAD DATA LOCAL INFILE {$file} INTO TABLE $table 
            CHARACTER SET latin1 
            FIELDS TERMINATED BY ',' ENCLOSED BY '\"' 
            LINES TERMINATED BY '\\n' 
            IGNORE 1 LINES;
            ";
            // Ejecutamos la consulta
            try {
                $loadData = $this->attach->prepare($query);
                $insert = $loadData->execute();
            } catch(PDOException $e) {
                //error_log("Error en LOAD DATA LOCAL INFILE: " . $e->getMessage());
                return false;
            }
            return $insert;
        }
    }
