<?php

namespace MissaelAnda\Grpc;

use Google\Protobuf\Internal\MapField;
use Google\Protobuf\Internal\Message;
use Google\Protobuf\Internal\RepeatedField;
use Illuminate\Support\Str;

abstract class Utils
{
    /**
     * Extracts the values from a message into a simple assoc array where the values are primitves
     *
     * @return array<string,mixed>
     */
    public static function extractMessageFields(Message $message): array
    {
        $reflection = new \ReflectionClass($message);

        return collect($reflection->getProperties())
            // The message must have a get<Field> method, if not it's an internal property and must be ignored
            ->filter(fn (\ReflectionProperty $property) => method_exists($message, 'get' . static::buildFieldMethod($property->name)))
            ->mapWithKeys(fn (\ReflectionProperty $property) => [
                $name = $property->getName() => static::extractField($message, $name),
            ])->all();
    }

    protected static function extractField(Message $message, string $field): mixed
    {
        $pascalField = static::buildFieldMethod($field);
        if (method_exists($message, $hasField = 'has' . $pascalField) && !$message->{$hasField}()) {
            return null;
        }

        $field = $message->{'get' . $pascalField}();
        return match (true) {
            $field instanceof RepeatedField, $field instanceof MapField => static::extractRepeatedFields($field),
            default => $field,
        };
    }

    protected static function buildFieldMethod(string $field): string
    {
        return (string)Str::of($field)->camel()->ucfirst();
    }

    /**
     * Get the items from a repeated field into a simple assoc array where the values are primitives
     *
     * @return array<string,mixed>
     */
    public static function extractRepeatedFields(RepeatedField|MapField $field): array
    {
        return array_map(fn ($item) => $item instanceof Message ?
            static::extractMessageFields($item) : $item, iterator_to_array($field));
    }
}
