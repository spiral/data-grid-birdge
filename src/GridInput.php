<?php

declare(strict_types=1);

namespace Spiral\DataGrid;

use Spiral\Core\Attribute\Scope;
use Spiral\Http\Request\InputManager;

#[Scope('http.request')]
final class GridInput implements InputInterface
{
    public function __construct(
        private InputManager $input,
    ) {
    }

    public function withNamespace(string $namespace): InputInterface
    {
        $input = clone $this;
        $input->input = $input->input->withPrefix($namespace);

        return $input;
    }

    public function hasValue(string $option): bool
    {
        return $this->input->input($option) !== null;
    }

    public function getValue(string $option, mixed $default = null): mixed
    {
        return $this->input->input($option, $default);
    }
}
