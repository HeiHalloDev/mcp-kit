- Every token belongs to a person; every call is logged in their name. A token never out-ranks its owner's permissions, and a service-client token can only read.
@if ($explicitOnly !== [])
- Changing what other people may do, and anything that reaches everyone at once, needs an ability named on the token ({{ implode(', ', array_map(fn ($a) => "`{$a}`", $explicitOnly)) }}) and a privileged owner.
@endif
