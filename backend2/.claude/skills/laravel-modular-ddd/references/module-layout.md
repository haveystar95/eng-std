# Module layout — worked example (`Collections`)

Read this when creating a new module or when unsure which file goes where.

```
app/Modules/Collections/
├── Domain/
│   ├── Entity/
│   │   ├── Collection.php              aggregate root
│   │   └── CollectionItem.php          entity inside the aggregate
│   ├── ValueObject/
│   │   ├── CollectionId.php
│   │   ├── CollectionType.php          enum: system|shared|custom
│   │   ├── Visibility.php              enum: private|link|public
│   │   └── LanguagePair.php
│   ├── Repository/
│   │   └── CollectionRepository.php    interface
│   ├── Service/
│   │   └── CollectionForker.php        copies items when a user forks a shared set
│   ├── Event/
│   │   ├── CollectionCreated.php
│   │   └── TermAddedToCollection.php
│   └── Exception/
│       ├── CollectionNotEditable.php
│       └── TermAlreadyInCollection.php
├── Application/
│   ├── Command/
│   │   ├── CreateCustomCollection.php + Handler
│   │   ├── AddTermToCollection.php + Handler
│   │   ├── RemoveTermFromCollection.php + Handler
│   │   ├── ReorderCollectionItems.php + Handler
│   │   ├── SubscribeToSharedCollection.php + Handler
│   │   └── ForkCollection.php + Handler
│   ├── Query/
│   │   ├── ListUserCollections.php + Handler   → CollectionSummaryView[]
│   │   ├── GetCollectionDetails.php + Handler  → CollectionDetailsView
│   │   └── SearchSharedCollections.php + Handler
│   └── Dto/
│       ├── CollectionSummaryView.php
│       └── CollectionDetailsView.php
├── Infrastructure/
│   ├── Eloquent/
│   │   ├── Model/CollectionModel.php           table: collections
│   │   ├── Model/CollectionItemModel.php       table: collection_items
│   │   ├── Model/UserCollectionModel.php       table: user_collections
│   │   ├── EloquentCollectionRepository.php
│   │   └── Mapper/CollectionMapper.php         model <-> entity
│   ├── Migration/2026_08_01_000100_create_collections_table.php
│   └── Provider/CollectionsServiceProvider.php
└── Presentation/
    └── Http/
        ├── Controller/CollectionController.php
        ├── Controller/CollectionItemController.php
        ├── Request/CreateCollectionRequest.php
        ├── Resource/CollectionResource.php
        ├── Policy/CollectionPolicy.php
        └── routes.php
```

## Aggregate example

```php
declare(strict_types=1);

namespace App\Modules\Collections\Domain\Entity;

final class Collection
{
    /** @var list<CollectionItem> */
    private array $items;

    private function __construct(
        public readonly CollectionId $id,
        private CollectionType $type,
        private ?UserId $ownerId,
        private string $title,
        private LanguagePair $languages,
        private Visibility $visibility,
        array $items = [],
    ) {
        $this->items = $items;
    }

    public static function createCustom(
        CollectionId $id, UserId $owner, string $title, LanguagePair $languages,
    ): self {
        return new self($id, CollectionType::Custom, $owner, $title, $languages, Visibility::Private);
    }

    public function addTerm(TermId $termId, ?string $note = null): void
    {
        foreach ($this->items as $item) {
            if ($item->termId->equals($termId)) {
                throw new TermAlreadyInCollection($this->id, $termId);
            }
        }

        $this->items[] = new CollectionItem($termId, position: count($this->items), note: $note);
    }

    public function assertEditableBy(UserId $actor): void
    {
        if ($this->type !== CollectionType::Custom || !$this->ownerId?->equals($actor)) {
            throw new CollectionNotEditable($this->id, $actor);
        }
    }
}
```

The aggregate holds `TermId`, not `Term`. Terms belong to the `Vocabulary` module;
crossing that line with an object reference is how modules quietly fuse together.

## Repository interface and implementation

```php
// Domain/Repository/CollectionRepository.php
interface CollectionRepository
{
    public function getById(CollectionId $id): Collection;       // throws if missing
    public function findById(CollectionId $id): ?Collection;
    public function save(Collection $collection): void;          // upsert aggregate + items
    public function nextId(): CollectionId;
}
```

The implementation lives in `Infrastructure/Eloquent`, uses models and a mapper, and is
the only place allowed to know that `collection_items` is a table.

## Service provider

```php
final class CollectionsServiceProvider extends ServiceProvider
{
    public array $bindings = [
        CollectionRepository::class => EloquentCollectionRepository::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migration');
        Route::middleware('api')->prefix('api/v1')
            ->group(__DIR__ . '/../../Presentation/Http/routes.php');
    }
}
```

Each module registers its own routes and migrations — adding a module means adding one
provider to `config/app.php`, nothing else.

## Creating a new module — checklist

1. Create the four layers, even if some start with one file.
2. Add the ServiceProvider and register it.
3. Add the module to `deptrac.yaml` with its allowed dependencies.
4. Write one architecture test asserting `Domain/` has no framework imports.
5. Add a short `README.md` in the module: what it owns, rich or thin, and why.
