<?php

declare(strict_types=1);

namespace Spiral\DataGrid\Specification\Filter;

use Cycle\Database\Injection;

class FragmentInjectionFilter extends InjectionFilter
{
    protected const INJECTION = Injection\Fragment::class;
}
