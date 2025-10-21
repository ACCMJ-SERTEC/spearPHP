<?php
    namespace core;

    abstract class Request
    {
        protected array $data;
        protected array $errors = [];
        protected array $messages = [];


        public function __construct(array $data){
            $this->data = $data;
        }

        abstract public function rules(): array;

        public function validate(): bool{
            $validator = new Validator();
    
            // Si hay mensajes personalizados, se pasan al validador
            $messages = $this->messages();
    
            if (!$validator->validate($this->data, $this->rules(), $messages)) {
                $this->errors = $validator->errors();
                return false;
            }
    
            return true;
        }

        public function errors(): array{
            return $this->errors;
        }

        public function messages(): array{
            return $this->messages;
        }

        public function input(string $key, $default = null){
            return $this->data[$key] ?? $default;
        }

        public function all(): array{
            return $this->data;
        }

        protected function sanitize(array $data): array{
            foreach ($data as $key => $value) {
                $data[$key] = $this->clean($value);
            }
            return $data;
        }

        protected function clean($value){
            if (!is_string($value)) return $value;

            $string = preg_replace('/\s+/', ' ', trim($value));
            $string = strip_tags($string);

            $reserved_words = [
            'select', 'insert', 'update', 'delete', 'drop', 'create', 
            'alter', 'table', 'where', 'from', 'into', 'join', 
            'group', 'order', 'by', 'having', 'null', 'and', 'or'
        ];
    
        $symbols = [
            '=', "'", '"', '==', '--', ';', '/*', '*/', '&', '|', '<', '>', 
            '%', '!', '(', ')', '{', '}', '[', ']', '^', ':', '<<', '>>', 
            '\\', '/',' '
        ];
    
        $escape_special_chars = function($symbol) {
            return preg_quote($symbol, '/'); 
        };
    
        $escaped_symbols = array_map($escape_special_chars, $symbols);
    
        $pattern = '/\b(' . implode('|', $reserved_words) . ')\b|(' . implode('|', $escaped_symbols) . ')/i';
    
        $input = preg_replace($pattern, '', $value);
    
        return $input;
        }
    }
