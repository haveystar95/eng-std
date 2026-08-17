// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_database.dart';

// ignore_for_file: type=lint
class $CollectionsTable extends Collections
    with TableInfo<$CollectionsTable, Collection> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CollectionsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _titleMeta = const VerificationMeta('title');
  @override
  late final GeneratedColumn<String> title = GeneratedColumn<String>(
    'title',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _descriptionMeta = const VerificationMeta(
    'description',
  );
  @override
  late final GeneratedColumn<String> description = GeneratedColumn<String>(
    'description',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _topicMeta = const VerificationMeta('topic');
  @override
  late final GeneratedColumn<String> topic = GeneratedColumn<String>(
    'topic',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _sourceLangMeta = const VerificationMeta(
    'sourceLang',
  );
  @override
  late final GeneratedColumn<String> sourceLang = GeneratedColumn<String>(
    'source_lang',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _targetLangMeta = const VerificationMeta(
    'targetLang',
  );
  @override
  late final GeneratedColumn<String> targetLang = GeneratedColumn<String>(
    'target_lang',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _itemsCountMeta = const VerificationMeta(
    'itemsCount',
  );
  @override
  late final GeneratedColumn<int> itemsCount = GeneratedColumn<int>(
    'items_count',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _sourceMeta = const VerificationMeta('source');
  @override
  late final GeneratedColumn<String> source = GeneratedColumn<String>(
    'source',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _typeMeta = const VerificationMeta('type');
  @override
  late final GeneratedColumn<String> type = GeneratedColumn<String>(
    'type',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imageUrlMeta = const VerificationMeta(
    'imageUrl',
  );
  @override
  late final GeneratedColumn<String> imageUrl = GeneratedColumn<String>(
    'image_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imageAuthorMeta = const VerificationMeta(
    'imageAuthor',
  );
  @override
  late final GeneratedColumn<String> imageAuthor = GeneratedColumn<String>(
    'image_author',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imageAuthorUrlMeta = const VerificationMeta(
    'imageAuthorUrl',
  );
  @override
  late final GeneratedColumn<String> imageAuthorUrl = GeneratedColumn<String>(
    'image_author_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    title,
    description,
    topic,
    sourceLang,
    targetLang,
    itemsCount,
    source,
    type,
    imageUrl,
    imageAuthor,
    imageAuthorUrl,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'collections';
  @override
  VerificationContext validateIntegrity(
    Insertable<Collection> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('title')) {
      context.handle(
        _titleMeta,
        title.isAcceptableOrUnknown(data['title']!, _titleMeta),
      );
    }
    if (data.containsKey('description')) {
      context.handle(
        _descriptionMeta,
        description.isAcceptableOrUnknown(
          data['description']!,
          _descriptionMeta,
        ),
      );
    }
    if (data.containsKey('topic')) {
      context.handle(
        _topicMeta,
        topic.isAcceptableOrUnknown(data['topic']!, _topicMeta),
      );
    }
    if (data.containsKey('source_lang')) {
      context.handle(
        _sourceLangMeta,
        sourceLang.isAcceptableOrUnknown(data['source_lang']!, _sourceLangMeta),
      );
    }
    if (data.containsKey('target_lang')) {
      context.handle(
        _targetLangMeta,
        targetLang.isAcceptableOrUnknown(data['target_lang']!, _targetLangMeta),
      );
    }
    if (data.containsKey('items_count')) {
      context.handle(
        _itemsCountMeta,
        itemsCount.isAcceptableOrUnknown(data['items_count']!, _itemsCountMeta),
      );
    }
    if (data.containsKey('source')) {
      context.handle(
        _sourceMeta,
        source.isAcceptableOrUnknown(data['source']!, _sourceMeta),
      );
    }
    if (data.containsKey('type')) {
      context.handle(
        _typeMeta,
        type.isAcceptableOrUnknown(data['type']!, _typeMeta),
      );
    }
    if (data.containsKey('image_url')) {
      context.handle(
        _imageUrlMeta,
        imageUrl.isAcceptableOrUnknown(data['image_url']!, _imageUrlMeta),
      );
    }
    if (data.containsKey('image_author')) {
      context.handle(
        _imageAuthorMeta,
        imageAuthor.isAcceptableOrUnknown(
          data['image_author']!,
          _imageAuthorMeta,
        ),
      );
    }
    if (data.containsKey('image_author_url')) {
      context.handle(
        _imageAuthorUrlMeta,
        imageAuthorUrl.isAcceptableOrUnknown(
          data['image_author_url']!,
          _imageAuthorUrlMeta,
        ),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Collection map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Collection(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      title: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}title'],
      ),
      description: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}description'],
      ),
      topic: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}topic'],
      ),
      sourceLang: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}source_lang'],
      ),
      targetLang: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}target_lang'],
      ),
      itemsCount: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}items_count'],
      )!,
      source: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}source'],
      ),
      type: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}type'],
      ),
      imageUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}image_url'],
      ),
      imageAuthor: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}image_author'],
      ),
      imageAuthorUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}image_author_url'],
      ),
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $CollectionsTable createAlias(String alias) {
    return $CollectionsTable(attachedDatabase, alias);
  }
}

class Collection extends DataClass implements Insertable<Collection> {
  final String id;
  final String? title;
  final String? description;
  final String? topic;
  final String? sourceLang;
  final String? targetLang;
  final int itemsCount;
  final String? source;
  final String? type;
  final String? imageUrl;
  final String? imageAuthor;
  final String? imageAuthorUrl;
  final DateTime updatedAt;
  const Collection({
    required this.id,
    this.title,
    this.description,
    this.topic,
    this.sourceLang,
    this.targetLang,
    required this.itemsCount,
    this.source,
    this.type,
    this.imageUrl,
    this.imageAuthor,
    this.imageAuthorUrl,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    if (!nullToAbsent || title != null) {
      map['title'] = Variable<String>(title);
    }
    if (!nullToAbsent || description != null) {
      map['description'] = Variable<String>(description);
    }
    if (!nullToAbsent || topic != null) {
      map['topic'] = Variable<String>(topic);
    }
    if (!nullToAbsent || sourceLang != null) {
      map['source_lang'] = Variable<String>(sourceLang);
    }
    if (!nullToAbsent || targetLang != null) {
      map['target_lang'] = Variable<String>(targetLang);
    }
    map['items_count'] = Variable<int>(itemsCount);
    if (!nullToAbsent || source != null) {
      map['source'] = Variable<String>(source);
    }
    if (!nullToAbsent || type != null) {
      map['type'] = Variable<String>(type);
    }
    if (!nullToAbsent || imageUrl != null) {
      map['image_url'] = Variable<String>(imageUrl);
    }
    if (!nullToAbsent || imageAuthor != null) {
      map['image_author'] = Variable<String>(imageAuthor);
    }
    if (!nullToAbsent || imageAuthorUrl != null) {
      map['image_author_url'] = Variable<String>(imageAuthorUrl);
    }
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  CollectionsCompanion toCompanion(bool nullToAbsent) {
    return CollectionsCompanion(
      id: Value(id),
      title: title == null && nullToAbsent
          ? const Value.absent()
          : Value(title),
      description: description == null && nullToAbsent
          ? const Value.absent()
          : Value(description),
      topic: topic == null && nullToAbsent
          ? const Value.absent()
          : Value(topic),
      sourceLang: sourceLang == null && nullToAbsent
          ? const Value.absent()
          : Value(sourceLang),
      targetLang: targetLang == null && nullToAbsent
          ? const Value.absent()
          : Value(targetLang),
      itemsCount: Value(itemsCount),
      source: source == null && nullToAbsent
          ? const Value.absent()
          : Value(source),
      type: type == null && nullToAbsent ? const Value.absent() : Value(type),
      imageUrl: imageUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(imageUrl),
      imageAuthor: imageAuthor == null && nullToAbsent
          ? const Value.absent()
          : Value(imageAuthor),
      imageAuthorUrl: imageAuthorUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(imageAuthorUrl),
      updatedAt: Value(updatedAt),
    );
  }

  factory Collection.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Collection(
      id: serializer.fromJson<String>(json['id']),
      title: serializer.fromJson<String?>(json['title']),
      description: serializer.fromJson<String?>(json['description']),
      topic: serializer.fromJson<String?>(json['topic']),
      sourceLang: serializer.fromJson<String?>(json['sourceLang']),
      targetLang: serializer.fromJson<String?>(json['targetLang']),
      itemsCount: serializer.fromJson<int>(json['itemsCount']),
      source: serializer.fromJson<String?>(json['source']),
      type: serializer.fromJson<String?>(json['type']),
      imageUrl: serializer.fromJson<String?>(json['imageUrl']),
      imageAuthor: serializer.fromJson<String?>(json['imageAuthor']),
      imageAuthorUrl: serializer.fromJson<String?>(json['imageAuthorUrl']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'title': serializer.toJson<String?>(title),
      'description': serializer.toJson<String?>(description),
      'topic': serializer.toJson<String?>(topic),
      'sourceLang': serializer.toJson<String?>(sourceLang),
      'targetLang': serializer.toJson<String?>(targetLang),
      'itemsCount': serializer.toJson<int>(itemsCount),
      'source': serializer.toJson<String?>(source),
      'type': serializer.toJson<String?>(type),
      'imageUrl': serializer.toJson<String?>(imageUrl),
      'imageAuthor': serializer.toJson<String?>(imageAuthor),
      'imageAuthorUrl': serializer.toJson<String?>(imageAuthorUrl),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  Collection copyWith({
    String? id,
    Value<String?> title = const Value.absent(),
    Value<String?> description = const Value.absent(),
    Value<String?> topic = const Value.absent(),
    Value<String?> sourceLang = const Value.absent(),
    Value<String?> targetLang = const Value.absent(),
    int? itemsCount,
    Value<String?> source = const Value.absent(),
    Value<String?> type = const Value.absent(),
    Value<String?> imageUrl = const Value.absent(),
    Value<String?> imageAuthor = const Value.absent(),
    Value<String?> imageAuthorUrl = const Value.absent(),
    DateTime? updatedAt,
  }) => Collection(
    id: id ?? this.id,
    title: title.present ? title.value : this.title,
    description: description.present ? description.value : this.description,
    topic: topic.present ? topic.value : this.topic,
    sourceLang: sourceLang.present ? sourceLang.value : this.sourceLang,
    targetLang: targetLang.present ? targetLang.value : this.targetLang,
    itemsCount: itemsCount ?? this.itemsCount,
    source: source.present ? source.value : this.source,
    type: type.present ? type.value : this.type,
    imageUrl: imageUrl.present ? imageUrl.value : this.imageUrl,
    imageAuthor: imageAuthor.present ? imageAuthor.value : this.imageAuthor,
    imageAuthorUrl: imageAuthorUrl.present
        ? imageAuthorUrl.value
        : this.imageAuthorUrl,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  Collection copyWithCompanion(CollectionsCompanion data) {
    return Collection(
      id: data.id.present ? data.id.value : this.id,
      title: data.title.present ? data.title.value : this.title,
      description: data.description.present
          ? data.description.value
          : this.description,
      topic: data.topic.present ? data.topic.value : this.topic,
      sourceLang: data.sourceLang.present
          ? data.sourceLang.value
          : this.sourceLang,
      targetLang: data.targetLang.present
          ? data.targetLang.value
          : this.targetLang,
      itemsCount: data.itemsCount.present
          ? data.itemsCount.value
          : this.itemsCount,
      source: data.source.present ? data.source.value : this.source,
      type: data.type.present ? data.type.value : this.type,
      imageUrl: data.imageUrl.present ? data.imageUrl.value : this.imageUrl,
      imageAuthor: data.imageAuthor.present
          ? data.imageAuthor.value
          : this.imageAuthor,
      imageAuthorUrl: data.imageAuthorUrl.present
          ? data.imageAuthorUrl.value
          : this.imageAuthorUrl,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Collection(')
          ..write('id: $id, ')
          ..write('title: $title, ')
          ..write('description: $description, ')
          ..write('topic: $topic, ')
          ..write('sourceLang: $sourceLang, ')
          ..write('targetLang: $targetLang, ')
          ..write('itemsCount: $itemsCount, ')
          ..write('source: $source, ')
          ..write('type: $type, ')
          ..write('imageUrl: $imageUrl, ')
          ..write('imageAuthor: $imageAuthor, ')
          ..write('imageAuthorUrl: $imageAuthorUrl, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    title,
    description,
    topic,
    sourceLang,
    targetLang,
    itemsCount,
    source,
    type,
    imageUrl,
    imageAuthor,
    imageAuthorUrl,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Collection &&
          other.id == this.id &&
          other.title == this.title &&
          other.description == this.description &&
          other.topic == this.topic &&
          other.sourceLang == this.sourceLang &&
          other.targetLang == this.targetLang &&
          other.itemsCount == this.itemsCount &&
          other.source == this.source &&
          other.type == this.type &&
          other.imageUrl == this.imageUrl &&
          other.imageAuthor == this.imageAuthor &&
          other.imageAuthorUrl == this.imageAuthorUrl &&
          other.updatedAt == this.updatedAt);
}

class CollectionsCompanion extends UpdateCompanion<Collection> {
  final Value<String> id;
  final Value<String?> title;
  final Value<String?> description;
  final Value<String?> topic;
  final Value<String?> sourceLang;
  final Value<String?> targetLang;
  final Value<int> itemsCount;
  final Value<String?> source;
  final Value<String?> type;
  final Value<String?> imageUrl;
  final Value<String?> imageAuthor;
  final Value<String?> imageAuthorUrl;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const CollectionsCompanion({
    this.id = const Value.absent(),
    this.title = const Value.absent(),
    this.description = const Value.absent(),
    this.topic = const Value.absent(),
    this.sourceLang = const Value.absent(),
    this.targetLang = const Value.absent(),
    this.itemsCount = const Value.absent(),
    this.source = const Value.absent(),
    this.type = const Value.absent(),
    this.imageUrl = const Value.absent(),
    this.imageAuthor = const Value.absent(),
    this.imageAuthorUrl = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CollectionsCompanion.insert({
    required String id,
    this.title = const Value.absent(),
    this.description = const Value.absent(),
    this.topic = const Value.absent(),
    this.sourceLang = const Value.absent(),
    this.targetLang = const Value.absent(),
    this.itemsCount = const Value.absent(),
    this.source = const Value.absent(),
    this.type = const Value.absent(),
    this.imageUrl = const Value.absent(),
    this.imageAuthor = const Value.absent(),
    this.imageAuthorUrl = const Value.absent(),
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : id = Value(id),
       updatedAt = Value(updatedAt);
  static Insertable<Collection> custom({
    Expression<String>? id,
    Expression<String>? title,
    Expression<String>? description,
    Expression<String>? topic,
    Expression<String>? sourceLang,
    Expression<String>? targetLang,
    Expression<int>? itemsCount,
    Expression<String>? source,
    Expression<String>? type,
    Expression<String>? imageUrl,
    Expression<String>? imageAuthor,
    Expression<String>? imageAuthorUrl,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (title != null) 'title': title,
      if (description != null) 'description': description,
      if (topic != null) 'topic': topic,
      if (sourceLang != null) 'source_lang': sourceLang,
      if (targetLang != null) 'target_lang': targetLang,
      if (itemsCount != null) 'items_count': itemsCount,
      if (source != null) 'source': source,
      if (type != null) 'type': type,
      if (imageUrl != null) 'image_url': imageUrl,
      if (imageAuthor != null) 'image_author': imageAuthor,
      if (imageAuthorUrl != null) 'image_author_url': imageAuthorUrl,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CollectionsCompanion copyWith({
    Value<String>? id,
    Value<String?>? title,
    Value<String?>? description,
    Value<String?>? topic,
    Value<String?>? sourceLang,
    Value<String?>? targetLang,
    Value<int>? itemsCount,
    Value<String?>? source,
    Value<String?>? type,
    Value<String?>? imageUrl,
    Value<String?>? imageAuthor,
    Value<String?>? imageAuthorUrl,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return CollectionsCompanion(
      id: id ?? this.id,
      title: title ?? this.title,
      description: description ?? this.description,
      topic: topic ?? this.topic,
      sourceLang: sourceLang ?? this.sourceLang,
      targetLang: targetLang ?? this.targetLang,
      itemsCount: itemsCount ?? this.itemsCount,
      source: source ?? this.source,
      type: type ?? this.type,
      imageUrl: imageUrl ?? this.imageUrl,
      imageAuthor: imageAuthor ?? this.imageAuthor,
      imageAuthorUrl: imageAuthorUrl ?? this.imageAuthorUrl,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (title.present) {
      map['title'] = Variable<String>(title.value);
    }
    if (description.present) {
      map['description'] = Variable<String>(description.value);
    }
    if (topic.present) {
      map['topic'] = Variable<String>(topic.value);
    }
    if (sourceLang.present) {
      map['source_lang'] = Variable<String>(sourceLang.value);
    }
    if (targetLang.present) {
      map['target_lang'] = Variable<String>(targetLang.value);
    }
    if (itemsCount.present) {
      map['items_count'] = Variable<int>(itemsCount.value);
    }
    if (source.present) {
      map['source'] = Variable<String>(source.value);
    }
    if (type.present) {
      map['type'] = Variable<String>(type.value);
    }
    if (imageUrl.present) {
      map['image_url'] = Variable<String>(imageUrl.value);
    }
    if (imageAuthor.present) {
      map['image_author'] = Variable<String>(imageAuthor.value);
    }
    if (imageAuthorUrl.present) {
      map['image_author_url'] = Variable<String>(imageAuthorUrl.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CollectionsCompanion(')
          ..write('id: $id, ')
          ..write('title: $title, ')
          ..write('description: $description, ')
          ..write('topic: $topic, ')
          ..write('sourceLang: $sourceLang, ')
          ..write('targetLang: $targetLang, ')
          ..write('itemsCount: $itemsCount, ')
          ..write('source: $source, ')
          ..write('type: $type, ')
          ..write('imageUrl: $imageUrl, ')
          ..write('imageAuthor: $imageAuthor, ')
          ..write('imageAuthorUrl: $imageAuthorUrl, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $CollectionItemsTable extends CollectionItems
    with TableInfo<$CollectionItemsTable, CollectionItem> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CollectionItemsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _collectionIdMeta = const VerificationMeta(
    'collectionId',
  );
  @override
  late final GeneratedColumn<String> collectionId = GeneratedColumn<String>(
    'collection_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _termIdMeta = const VerificationMeta('termId');
  @override
  late final GeneratedColumn<String> termId = GeneratedColumn<String>(
    'term_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _positionMeta = const VerificationMeta(
    'position',
  );
  @override
  late final GeneratedColumn<int> position = GeneratedColumn<int>(
    'position',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _noteMeta = const VerificationMeta('note');
  @override
  late final GeneratedColumn<String> note = GeneratedColumn<String>(
    'note',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    collectionId,
    termId,
    position,
    note,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'collection_items';
  @override
  VerificationContext validateIntegrity(
    Insertable<CollectionItem> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('collection_id')) {
      context.handle(
        _collectionIdMeta,
        collectionId.isAcceptableOrUnknown(
          data['collection_id']!,
          _collectionIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_collectionIdMeta);
    }
    if (data.containsKey('term_id')) {
      context.handle(
        _termIdMeta,
        termId.isAcceptableOrUnknown(data['term_id']!, _termIdMeta),
      );
    } else if (isInserting) {
      context.missing(_termIdMeta);
    }
    if (data.containsKey('position')) {
      context.handle(
        _positionMeta,
        position.isAcceptableOrUnknown(data['position']!, _positionMeta),
      );
    }
    if (data.containsKey('note')) {
      context.handle(
        _noteMeta,
        note.isAcceptableOrUnknown(data['note']!, _noteMeta),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {collectionId, termId};
  @override
  CollectionItem map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CollectionItem(
      collectionId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}collection_id'],
      )!,
      termId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}term_id'],
      )!,
      position: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}position'],
      )!,
      note: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}note'],
      ),
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $CollectionItemsTable createAlias(String alias) {
    return $CollectionItemsTable(attachedDatabase, alias);
  }
}

class CollectionItem extends DataClass implements Insertable<CollectionItem> {
  final String collectionId;
  final String termId;
  final int position;
  final String? note;
  final DateTime updatedAt;
  const CollectionItem({
    required this.collectionId,
    required this.termId,
    required this.position,
    this.note,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['collection_id'] = Variable<String>(collectionId);
    map['term_id'] = Variable<String>(termId);
    map['position'] = Variable<int>(position);
    if (!nullToAbsent || note != null) {
      map['note'] = Variable<String>(note);
    }
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  CollectionItemsCompanion toCompanion(bool nullToAbsent) {
    return CollectionItemsCompanion(
      collectionId: Value(collectionId),
      termId: Value(termId),
      position: Value(position),
      note: note == null && nullToAbsent ? const Value.absent() : Value(note),
      updatedAt: Value(updatedAt),
    );
  }

  factory CollectionItem.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CollectionItem(
      collectionId: serializer.fromJson<String>(json['collectionId']),
      termId: serializer.fromJson<String>(json['termId']),
      position: serializer.fromJson<int>(json['position']),
      note: serializer.fromJson<String?>(json['note']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'collectionId': serializer.toJson<String>(collectionId),
      'termId': serializer.toJson<String>(termId),
      'position': serializer.toJson<int>(position),
      'note': serializer.toJson<String?>(note),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  CollectionItem copyWith({
    String? collectionId,
    String? termId,
    int? position,
    Value<String?> note = const Value.absent(),
    DateTime? updatedAt,
  }) => CollectionItem(
    collectionId: collectionId ?? this.collectionId,
    termId: termId ?? this.termId,
    position: position ?? this.position,
    note: note.present ? note.value : this.note,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  CollectionItem copyWithCompanion(CollectionItemsCompanion data) {
    return CollectionItem(
      collectionId: data.collectionId.present
          ? data.collectionId.value
          : this.collectionId,
      termId: data.termId.present ? data.termId.value : this.termId,
      position: data.position.present ? data.position.value : this.position,
      note: data.note.present ? data.note.value : this.note,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CollectionItem(')
          ..write('collectionId: $collectionId, ')
          ..write('termId: $termId, ')
          ..write('position: $position, ')
          ..write('note: $note, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(collectionId, termId, position, note, updatedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CollectionItem &&
          other.collectionId == this.collectionId &&
          other.termId == this.termId &&
          other.position == this.position &&
          other.note == this.note &&
          other.updatedAt == this.updatedAt);
}

class CollectionItemsCompanion extends UpdateCompanion<CollectionItem> {
  final Value<String> collectionId;
  final Value<String> termId;
  final Value<int> position;
  final Value<String?> note;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const CollectionItemsCompanion({
    this.collectionId = const Value.absent(),
    this.termId = const Value.absent(),
    this.position = const Value.absent(),
    this.note = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CollectionItemsCompanion.insert({
    required String collectionId,
    required String termId,
    this.position = const Value.absent(),
    this.note = const Value.absent(),
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : collectionId = Value(collectionId),
       termId = Value(termId),
       updatedAt = Value(updatedAt);
  static Insertable<CollectionItem> custom({
    Expression<String>? collectionId,
    Expression<String>? termId,
    Expression<int>? position,
    Expression<String>? note,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (collectionId != null) 'collection_id': collectionId,
      if (termId != null) 'term_id': termId,
      if (position != null) 'position': position,
      if (note != null) 'note': note,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CollectionItemsCompanion copyWith({
    Value<String>? collectionId,
    Value<String>? termId,
    Value<int>? position,
    Value<String?>? note,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return CollectionItemsCompanion(
      collectionId: collectionId ?? this.collectionId,
      termId: termId ?? this.termId,
      position: position ?? this.position,
      note: note ?? this.note,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (collectionId.present) {
      map['collection_id'] = Variable<String>(collectionId.value);
    }
    if (termId.present) {
      map['term_id'] = Variable<String>(termId.value);
    }
    if (position.present) {
      map['position'] = Variable<int>(position.value);
    }
    if (note.present) {
      map['note'] = Variable<String>(note.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CollectionItemsCompanion(')
          ..write('collectionId: $collectionId, ')
          ..write('termId: $termId, ')
          ..write('position: $position, ')
          ..write('note: $note, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $TermsTable extends Terms with TableInfo<$TermsTable, Term> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $TermsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _termTextMeta = const VerificationMeta(
    'termText',
  );
  @override
  late final GeneratedColumn<String> termText = GeneratedColumn<String>(
    'text',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _typeMeta = const VerificationMeta('type');
  @override
  late final GeneratedColumn<String> type = GeneratedColumn<String>(
    'type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('word'),
  );
  static const VerificationMeta _transcriptionMeta = const VerificationMeta(
    'transcription',
  );
  @override
  late final GeneratedColumn<String> transcription = GeneratedColumn<String>(
    'transcription',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _translationMeta = const VerificationMeta(
    'translation',
  );
  @override
  late final GeneratedColumn<String> translation = GeneratedColumn<String>(
    'translation',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _exampleMeta = const VerificationMeta(
    'example',
  );
  @override
  late final GeneratedColumn<String> example = GeneratedColumn<String>(
    'example',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _exampleTranslationMeta =
      const VerificationMeta('exampleTranslation');
  @override
  late final GeneratedColumn<String> exampleTranslation =
      GeneratedColumn<String>(
        'example_translation',
        aliasedName,
        true,
        type: DriftSqlType.string,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _imageUrlMeta = const VerificationMeta(
    'imageUrl',
  );
  @override
  late final GeneratedColumn<String> imageUrl = GeneratedColumn<String>(
    'image_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imageAuthorMeta = const VerificationMeta(
    'imageAuthor',
  );
  @override
  late final GeneratedColumn<String> imageAuthor = GeneratedColumn<String>(
    'image_author',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imageAuthorUrlMeta = const VerificationMeta(
    'imageAuthorUrl',
  );
  @override
  late final GeneratedColumn<String> imageAuthorUrl = GeneratedColumn<String>(
    'image_author_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _acceptedVariantsMeta = const VerificationMeta(
    'acceptedVariants',
  );
  @override
  late final GeneratedColumn<String> acceptedVariants = GeneratedColumn<String>(
    'accepted_variants',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _exampleDistractorsMeta =
      const VerificationMeta('exampleDistractors');
  @override
  late final GeneratedColumn<String> exampleDistractors =
      GeneratedColumn<String>(
        'example_distractors',
        aliasedName,
        true,
        type: DriftSqlType.string,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    termText,
    type,
    transcription,
    translation,
    example,
    exampleTranslation,
    imageUrl,
    imageAuthor,
    imageAuthorUrl,
    acceptedVariants,
    exampleDistractors,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'terms';
  @override
  VerificationContext validateIntegrity(
    Insertable<Term> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('text')) {
      context.handle(
        _termTextMeta,
        termText.isAcceptableOrUnknown(data['text']!, _termTextMeta),
      );
    }
    if (data.containsKey('type')) {
      context.handle(
        _typeMeta,
        type.isAcceptableOrUnknown(data['type']!, _typeMeta),
      );
    }
    if (data.containsKey('transcription')) {
      context.handle(
        _transcriptionMeta,
        transcription.isAcceptableOrUnknown(
          data['transcription']!,
          _transcriptionMeta,
        ),
      );
    }
    if (data.containsKey('translation')) {
      context.handle(
        _translationMeta,
        translation.isAcceptableOrUnknown(
          data['translation']!,
          _translationMeta,
        ),
      );
    }
    if (data.containsKey('example')) {
      context.handle(
        _exampleMeta,
        example.isAcceptableOrUnknown(data['example']!, _exampleMeta),
      );
    }
    if (data.containsKey('example_translation')) {
      context.handle(
        _exampleTranslationMeta,
        exampleTranslation.isAcceptableOrUnknown(
          data['example_translation']!,
          _exampleTranslationMeta,
        ),
      );
    }
    if (data.containsKey('image_url')) {
      context.handle(
        _imageUrlMeta,
        imageUrl.isAcceptableOrUnknown(data['image_url']!, _imageUrlMeta),
      );
    }
    if (data.containsKey('image_author')) {
      context.handle(
        _imageAuthorMeta,
        imageAuthor.isAcceptableOrUnknown(
          data['image_author']!,
          _imageAuthorMeta,
        ),
      );
    }
    if (data.containsKey('image_author_url')) {
      context.handle(
        _imageAuthorUrlMeta,
        imageAuthorUrl.isAcceptableOrUnknown(
          data['image_author_url']!,
          _imageAuthorUrlMeta,
        ),
      );
    }
    if (data.containsKey('accepted_variants')) {
      context.handle(
        _acceptedVariantsMeta,
        acceptedVariants.isAcceptableOrUnknown(
          data['accepted_variants']!,
          _acceptedVariantsMeta,
        ),
      );
    }
    if (data.containsKey('example_distractors')) {
      context.handle(
        _exampleDistractorsMeta,
        exampleDistractors.isAcceptableOrUnknown(
          data['example_distractors']!,
          _exampleDistractorsMeta,
        ),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Term map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Term(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      termText: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}text'],
      ),
      type: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}type'],
      )!,
      transcription: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}transcription'],
      ),
      translation: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}translation'],
      ),
      example: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}example'],
      ),
      exampleTranslation: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}example_translation'],
      ),
      imageUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}image_url'],
      ),
      imageAuthor: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}image_author'],
      ),
      imageAuthorUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}image_author_url'],
      ),
      acceptedVariants: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}accepted_variants'],
      ),
      exampleDistractors: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}example_distractors'],
      ),
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $TermsTable createAlias(String alias) {
    return $TermsTable(attachedDatabase, alias);
  }
}

class Term extends DataClass implements Insertable<Term> {
  final String id;
  final String? termText;
  final String type;
  final String? transcription;
  final String? translation;
  final String? example;
  final String? exampleTranslation;
  final String? imageUrl;
  final String? imageAuthor;
  final String? imageAuthorUrl;

  /// Other answers that also count as correct, as a JSON array of strings. Needed OFFLINE: the
  /// instant check grades against `{termText} ∪ acceptedVariants`, so a device without them would
  /// reject an answer the server accepts.
  ///
  /// JSON in a column rather than a child table on purpose — `/sync` sends a term's whole variant
  /// list on every term upsert, so one write replaces the whole set atomically and there is no
  /// orphan row to clean up. A child table would buy queryability nothing here needs.
  final String? acceptedVariants;

  /// Wrong versions of [example], as a JSON array of objects. Mirrored ahead of the trainer that
  /// reads them, so it works offline the day it is switched on.
  final String? exampleDistractors;
  final DateTime updatedAt;
  const Term({
    required this.id,
    this.termText,
    required this.type,
    this.transcription,
    this.translation,
    this.example,
    this.exampleTranslation,
    this.imageUrl,
    this.imageAuthor,
    this.imageAuthorUrl,
    this.acceptedVariants,
    this.exampleDistractors,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    if (!nullToAbsent || termText != null) {
      map['text'] = Variable<String>(termText);
    }
    map['type'] = Variable<String>(type);
    if (!nullToAbsent || transcription != null) {
      map['transcription'] = Variable<String>(transcription);
    }
    if (!nullToAbsent || translation != null) {
      map['translation'] = Variable<String>(translation);
    }
    if (!nullToAbsent || example != null) {
      map['example'] = Variable<String>(example);
    }
    if (!nullToAbsent || exampleTranslation != null) {
      map['example_translation'] = Variable<String>(exampleTranslation);
    }
    if (!nullToAbsent || imageUrl != null) {
      map['image_url'] = Variable<String>(imageUrl);
    }
    if (!nullToAbsent || imageAuthor != null) {
      map['image_author'] = Variable<String>(imageAuthor);
    }
    if (!nullToAbsent || imageAuthorUrl != null) {
      map['image_author_url'] = Variable<String>(imageAuthorUrl);
    }
    if (!nullToAbsent || acceptedVariants != null) {
      map['accepted_variants'] = Variable<String>(acceptedVariants);
    }
    if (!nullToAbsent || exampleDistractors != null) {
      map['example_distractors'] = Variable<String>(exampleDistractors);
    }
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  TermsCompanion toCompanion(bool nullToAbsent) {
    return TermsCompanion(
      id: Value(id),
      termText: termText == null && nullToAbsent
          ? const Value.absent()
          : Value(termText),
      type: Value(type),
      transcription: transcription == null && nullToAbsent
          ? const Value.absent()
          : Value(transcription),
      translation: translation == null && nullToAbsent
          ? const Value.absent()
          : Value(translation),
      example: example == null && nullToAbsent
          ? const Value.absent()
          : Value(example),
      exampleTranslation: exampleTranslation == null && nullToAbsent
          ? const Value.absent()
          : Value(exampleTranslation),
      imageUrl: imageUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(imageUrl),
      imageAuthor: imageAuthor == null && nullToAbsent
          ? const Value.absent()
          : Value(imageAuthor),
      imageAuthorUrl: imageAuthorUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(imageAuthorUrl),
      acceptedVariants: acceptedVariants == null && nullToAbsent
          ? const Value.absent()
          : Value(acceptedVariants),
      exampleDistractors: exampleDistractors == null && nullToAbsent
          ? const Value.absent()
          : Value(exampleDistractors),
      updatedAt: Value(updatedAt),
    );
  }

  factory Term.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Term(
      id: serializer.fromJson<String>(json['id']),
      termText: serializer.fromJson<String?>(json['termText']),
      type: serializer.fromJson<String>(json['type']),
      transcription: serializer.fromJson<String?>(json['transcription']),
      translation: serializer.fromJson<String?>(json['translation']),
      example: serializer.fromJson<String?>(json['example']),
      exampleTranslation: serializer.fromJson<String?>(
        json['exampleTranslation'],
      ),
      imageUrl: serializer.fromJson<String?>(json['imageUrl']),
      imageAuthor: serializer.fromJson<String?>(json['imageAuthor']),
      imageAuthorUrl: serializer.fromJson<String?>(json['imageAuthorUrl']),
      acceptedVariants: serializer.fromJson<String?>(json['acceptedVariants']),
      exampleDistractors: serializer.fromJson<String?>(
        json['exampleDistractors'],
      ),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'termText': serializer.toJson<String?>(termText),
      'type': serializer.toJson<String>(type),
      'transcription': serializer.toJson<String?>(transcription),
      'translation': serializer.toJson<String?>(translation),
      'example': serializer.toJson<String?>(example),
      'exampleTranslation': serializer.toJson<String?>(exampleTranslation),
      'imageUrl': serializer.toJson<String?>(imageUrl),
      'imageAuthor': serializer.toJson<String?>(imageAuthor),
      'imageAuthorUrl': serializer.toJson<String?>(imageAuthorUrl),
      'acceptedVariants': serializer.toJson<String?>(acceptedVariants),
      'exampleDistractors': serializer.toJson<String?>(exampleDistractors),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  Term copyWith({
    String? id,
    Value<String?> termText = const Value.absent(),
    String? type,
    Value<String?> transcription = const Value.absent(),
    Value<String?> translation = const Value.absent(),
    Value<String?> example = const Value.absent(),
    Value<String?> exampleTranslation = const Value.absent(),
    Value<String?> imageUrl = const Value.absent(),
    Value<String?> imageAuthor = const Value.absent(),
    Value<String?> imageAuthorUrl = const Value.absent(),
    Value<String?> acceptedVariants = const Value.absent(),
    Value<String?> exampleDistractors = const Value.absent(),
    DateTime? updatedAt,
  }) => Term(
    id: id ?? this.id,
    termText: termText.present ? termText.value : this.termText,
    type: type ?? this.type,
    transcription: transcription.present
        ? transcription.value
        : this.transcription,
    translation: translation.present ? translation.value : this.translation,
    example: example.present ? example.value : this.example,
    exampleTranslation: exampleTranslation.present
        ? exampleTranslation.value
        : this.exampleTranslation,
    imageUrl: imageUrl.present ? imageUrl.value : this.imageUrl,
    imageAuthor: imageAuthor.present ? imageAuthor.value : this.imageAuthor,
    imageAuthorUrl: imageAuthorUrl.present
        ? imageAuthorUrl.value
        : this.imageAuthorUrl,
    acceptedVariants: acceptedVariants.present
        ? acceptedVariants.value
        : this.acceptedVariants,
    exampleDistractors: exampleDistractors.present
        ? exampleDistractors.value
        : this.exampleDistractors,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  Term copyWithCompanion(TermsCompanion data) {
    return Term(
      id: data.id.present ? data.id.value : this.id,
      termText: data.termText.present ? data.termText.value : this.termText,
      type: data.type.present ? data.type.value : this.type,
      transcription: data.transcription.present
          ? data.transcription.value
          : this.transcription,
      translation: data.translation.present
          ? data.translation.value
          : this.translation,
      example: data.example.present ? data.example.value : this.example,
      exampleTranslation: data.exampleTranslation.present
          ? data.exampleTranslation.value
          : this.exampleTranslation,
      imageUrl: data.imageUrl.present ? data.imageUrl.value : this.imageUrl,
      imageAuthor: data.imageAuthor.present
          ? data.imageAuthor.value
          : this.imageAuthor,
      imageAuthorUrl: data.imageAuthorUrl.present
          ? data.imageAuthorUrl.value
          : this.imageAuthorUrl,
      acceptedVariants: data.acceptedVariants.present
          ? data.acceptedVariants.value
          : this.acceptedVariants,
      exampleDistractors: data.exampleDistractors.present
          ? data.exampleDistractors.value
          : this.exampleDistractors,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Term(')
          ..write('id: $id, ')
          ..write('termText: $termText, ')
          ..write('type: $type, ')
          ..write('transcription: $transcription, ')
          ..write('translation: $translation, ')
          ..write('example: $example, ')
          ..write('exampleTranslation: $exampleTranslation, ')
          ..write('imageUrl: $imageUrl, ')
          ..write('imageAuthor: $imageAuthor, ')
          ..write('imageAuthorUrl: $imageAuthorUrl, ')
          ..write('acceptedVariants: $acceptedVariants, ')
          ..write('exampleDistractors: $exampleDistractors, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    termText,
    type,
    transcription,
    translation,
    example,
    exampleTranslation,
    imageUrl,
    imageAuthor,
    imageAuthorUrl,
    acceptedVariants,
    exampleDistractors,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Term &&
          other.id == this.id &&
          other.termText == this.termText &&
          other.type == this.type &&
          other.transcription == this.transcription &&
          other.translation == this.translation &&
          other.example == this.example &&
          other.exampleTranslation == this.exampleTranslation &&
          other.imageUrl == this.imageUrl &&
          other.imageAuthor == this.imageAuthor &&
          other.imageAuthorUrl == this.imageAuthorUrl &&
          other.acceptedVariants == this.acceptedVariants &&
          other.exampleDistractors == this.exampleDistractors &&
          other.updatedAt == this.updatedAt);
}

class TermsCompanion extends UpdateCompanion<Term> {
  final Value<String> id;
  final Value<String?> termText;
  final Value<String> type;
  final Value<String?> transcription;
  final Value<String?> translation;
  final Value<String?> example;
  final Value<String?> exampleTranslation;
  final Value<String?> imageUrl;
  final Value<String?> imageAuthor;
  final Value<String?> imageAuthorUrl;
  final Value<String?> acceptedVariants;
  final Value<String?> exampleDistractors;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const TermsCompanion({
    this.id = const Value.absent(),
    this.termText = const Value.absent(),
    this.type = const Value.absent(),
    this.transcription = const Value.absent(),
    this.translation = const Value.absent(),
    this.example = const Value.absent(),
    this.exampleTranslation = const Value.absent(),
    this.imageUrl = const Value.absent(),
    this.imageAuthor = const Value.absent(),
    this.imageAuthorUrl = const Value.absent(),
    this.acceptedVariants = const Value.absent(),
    this.exampleDistractors = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  TermsCompanion.insert({
    required String id,
    this.termText = const Value.absent(),
    this.type = const Value.absent(),
    this.transcription = const Value.absent(),
    this.translation = const Value.absent(),
    this.example = const Value.absent(),
    this.exampleTranslation = const Value.absent(),
    this.imageUrl = const Value.absent(),
    this.imageAuthor = const Value.absent(),
    this.imageAuthorUrl = const Value.absent(),
    this.acceptedVariants = const Value.absent(),
    this.exampleDistractors = const Value.absent(),
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : id = Value(id),
       updatedAt = Value(updatedAt);
  static Insertable<Term> custom({
    Expression<String>? id,
    Expression<String>? termText,
    Expression<String>? type,
    Expression<String>? transcription,
    Expression<String>? translation,
    Expression<String>? example,
    Expression<String>? exampleTranslation,
    Expression<String>? imageUrl,
    Expression<String>? imageAuthor,
    Expression<String>? imageAuthorUrl,
    Expression<String>? acceptedVariants,
    Expression<String>? exampleDistractors,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (termText != null) 'text': termText,
      if (type != null) 'type': type,
      if (transcription != null) 'transcription': transcription,
      if (translation != null) 'translation': translation,
      if (example != null) 'example': example,
      if (exampleTranslation != null) 'example_translation': exampleTranslation,
      if (imageUrl != null) 'image_url': imageUrl,
      if (imageAuthor != null) 'image_author': imageAuthor,
      if (imageAuthorUrl != null) 'image_author_url': imageAuthorUrl,
      if (acceptedVariants != null) 'accepted_variants': acceptedVariants,
      if (exampleDistractors != null) 'example_distractors': exampleDistractors,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  TermsCompanion copyWith({
    Value<String>? id,
    Value<String?>? termText,
    Value<String>? type,
    Value<String?>? transcription,
    Value<String?>? translation,
    Value<String?>? example,
    Value<String?>? exampleTranslation,
    Value<String?>? imageUrl,
    Value<String?>? imageAuthor,
    Value<String?>? imageAuthorUrl,
    Value<String?>? acceptedVariants,
    Value<String?>? exampleDistractors,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return TermsCompanion(
      id: id ?? this.id,
      termText: termText ?? this.termText,
      type: type ?? this.type,
      transcription: transcription ?? this.transcription,
      translation: translation ?? this.translation,
      example: example ?? this.example,
      exampleTranslation: exampleTranslation ?? this.exampleTranslation,
      imageUrl: imageUrl ?? this.imageUrl,
      imageAuthor: imageAuthor ?? this.imageAuthor,
      imageAuthorUrl: imageAuthorUrl ?? this.imageAuthorUrl,
      acceptedVariants: acceptedVariants ?? this.acceptedVariants,
      exampleDistractors: exampleDistractors ?? this.exampleDistractors,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (termText.present) {
      map['text'] = Variable<String>(termText.value);
    }
    if (type.present) {
      map['type'] = Variable<String>(type.value);
    }
    if (transcription.present) {
      map['transcription'] = Variable<String>(transcription.value);
    }
    if (translation.present) {
      map['translation'] = Variable<String>(translation.value);
    }
    if (example.present) {
      map['example'] = Variable<String>(example.value);
    }
    if (exampleTranslation.present) {
      map['example_translation'] = Variable<String>(exampleTranslation.value);
    }
    if (imageUrl.present) {
      map['image_url'] = Variable<String>(imageUrl.value);
    }
    if (imageAuthor.present) {
      map['image_author'] = Variable<String>(imageAuthor.value);
    }
    if (imageAuthorUrl.present) {
      map['image_author_url'] = Variable<String>(imageAuthorUrl.value);
    }
    if (acceptedVariants.present) {
      map['accepted_variants'] = Variable<String>(acceptedVariants.value);
    }
    if (exampleDistractors.present) {
      map['example_distractors'] = Variable<String>(exampleDistractors.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('TermsCompanion(')
          ..write('id: $id, ')
          ..write('termText: $termText, ')
          ..write('type: $type, ')
          ..write('transcription: $transcription, ')
          ..write('translation: $translation, ')
          ..write('example: $example, ')
          ..write('exampleTranslation: $exampleTranslation, ')
          ..write('imageUrl: $imageUrl, ')
          ..write('imageAuthor: $imageAuthor, ')
          ..write('imageAuthorUrl: $imageAuthorUrl, ')
          ..write('acceptedVariants: $acceptedVariants, ')
          ..write('exampleDistractors: $exampleDistractors, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $TermProgressTable extends TermProgress
    with TableInfo<$TermProgressTable, TermProgressData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $TermProgressTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _termIdMeta = const VerificationMeta('termId');
  @override
  late final GeneratedColumn<String> termId = GeneratedColumn<String>(
    'term_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _stateMeta = const VerificationMeta('state');
  @override
  late final GeneratedColumn<String> state = GeneratedColumn<String>(
    'state',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('new'),
  );
  static const VerificationMeta _easeFactorMeta = const VerificationMeta(
    'easeFactor',
  );
  @override
  late final GeneratedColumn<double> easeFactor = GeneratedColumn<double>(
    'ease_factor',
    aliasedName,
    false,
    type: DriftSqlType.double,
    requiredDuringInsert: false,
    defaultValue: const Constant(2.5),
  );
  static const VerificationMeta _intervalDaysMeta = const VerificationMeta(
    'intervalDays',
  );
  @override
  late final GeneratedColumn<int> intervalDays = GeneratedColumn<int>(
    'interval_days',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _dueAtMeta = const VerificationMeta('dueAt');
  @override
  late final GeneratedColumn<DateTime> dueAt = GeneratedColumn<DateTime>(
    'due_at',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _repsMeta = const VerificationMeta('reps');
  @override
  late final GeneratedColumn<int> reps = GeneratedColumn<int>(
    'reps',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _lapsesMeta = const VerificationMeta('lapses');
  @override
  late final GeneratedColumn<int> lapses = GeneratedColumn<int>(
    'lapses',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _lastReviewedAtMeta = const VerificationMeta(
    'lastReviewedAt',
  );
  @override
  late final GeneratedColumn<DateTime> lastReviewedAt =
      GeneratedColumn<DateTime>(
        'last_reviewed_at',
        aliasedName,
        true,
        type: DriftSqlType.dateTime,
        requiredDuringInsert: false,
      );
  static const VerificationMeta _acquisitionMeta = const VerificationMeta(
    'acquisition',
  );
  @override
  late final GeneratedColumn<String> acquisition = GeneratedColumn<String>(
    'acquisition',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('graduated'),
  );
  static const VerificationMeta _learningStepMeta = const VerificationMeta(
    'learningStep',
  );
  @override
  late final GeneratedColumn<int> learningStep = GeneratedColumn<int>(
    'learning_step',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    termId,
    state,
    easeFactor,
    intervalDays,
    dueAt,
    reps,
    lapses,
    lastReviewedAt,
    acquisition,
    learningStep,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'term_progress';
  @override
  VerificationContext validateIntegrity(
    Insertable<TermProgressData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('term_id')) {
      context.handle(
        _termIdMeta,
        termId.isAcceptableOrUnknown(data['term_id']!, _termIdMeta),
      );
    } else if (isInserting) {
      context.missing(_termIdMeta);
    }
    if (data.containsKey('state')) {
      context.handle(
        _stateMeta,
        state.isAcceptableOrUnknown(data['state']!, _stateMeta),
      );
    }
    if (data.containsKey('ease_factor')) {
      context.handle(
        _easeFactorMeta,
        easeFactor.isAcceptableOrUnknown(data['ease_factor']!, _easeFactorMeta),
      );
    }
    if (data.containsKey('interval_days')) {
      context.handle(
        _intervalDaysMeta,
        intervalDays.isAcceptableOrUnknown(
          data['interval_days']!,
          _intervalDaysMeta,
        ),
      );
    }
    if (data.containsKey('due_at')) {
      context.handle(
        _dueAtMeta,
        dueAt.isAcceptableOrUnknown(data['due_at']!, _dueAtMeta),
      );
    }
    if (data.containsKey('reps')) {
      context.handle(
        _repsMeta,
        reps.isAcceptableOrUnknown(data['reps']!, _repsMeta),
      );
    }
    if (data.containsKey('lapses')) {
      context.handle(
        _lapsesMeta,
        lapses.isAcceptableOrUnknown(data['lapses']!, _lapsesMeta),
      );
    }
    if (data.containsKey('last_reviewed_at')) {
      context.handle(
        _lastReviewedAtMeta,
        lastReviewedAt.isAcceptableOrUnknown(
          data['last_reviewed_at']!,
          _lastReviewedAtMeta,
        ),
      );
    }
    if (data.containsKey('acquisition')) {
      context.handle(
        _acquisitionMeta,
        acquisition.isAcceptableOrUnknown(
          data['acquisition']!,
          _acquisitionMeta,
        ),
      );
    }
    if (data.containsKey('learning_step')) {
      context.handle(
        _learningStepMeta,
        learningStep.isAcceptableOrUnknown(
          data['learning_step']!,
          _learningStepMeta,
        ),
      );
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {termId};
  @override
  TermProgressData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return TermProgressData(
      termId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}term_id'],
      )!,
      state: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}state'],
      )!,
      easeFactor: attachedDatabase.typeMapping.read(
        DriftSqlType.double,
        data['${effectivePrefix}ease_factor'],
      )!,
      intervalDays: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}interval_days'],
      )!,
      dueAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}due_at'],
      ),
      reps: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}reps'],
      )!,
      lapses: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}lapses'],
      )!,
      lastReviewedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}last_reviewed_at'],
      ),
      acquisition: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}acquisition'],
      )!,
      learningStep: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}learning_step'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $TermProgressTable createAlias(String alias) {
    return $TermProgressTable(attachedDatabase, alias);
  }
}

class TermProgressData extends DataClass
    implements Insertable<TermProgressData> {
  final String termId;
  final String state;
  final double easeFactor;
  final int intervalDays;
  final DateTime? dueAt;
  final int reps;
  final int lapses;
  final DateTime? lastReviewedAt;

  /// The acquisition ladder: `new` (never shown) | `learning` (on the recognition rungs) |
  /// `graduated`. Defaults to `graduated` for rows that already existed when the ladder landed —
  /// the safe direction, since the alternative pushes a known word back to an intro card.
  final String acquisition;

  /// The rung while [acquisition] is `learning` (1 or 2). Not derivable from [reps]: a failed
  /// recognition step is re-queued as the same step but is still logged.
  final int learningStep;
  final DateTime updatedAt;
  const TermProgressData({
    required this.termId,
    required this.state,
    required this.easeFactor,
    required this.intervalDays,
    this.dueAt,
    required this.reps,
    required this.lapses,
    this.lastReviewedAt,
    required this.acquisition,
    required this.learningStep,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['term_id'] = Variable<String>(termId);
    map['state'] = Variable<String>(state);
    map['ease_factor'] = Variable<double>(easeFactor);
    map['interval_days'] = Variable<int>(intervalDays);
    if (!nullToAbsent || dueAt != null) {
      map['due_at'] = Variable<DateTime>(dueAt);
    }
    map['reps'] = Variable<int>(reps);
    map['lapses'] = Variable<int>(lapses);
    if (!nullToAbsent || lastReviewedAt != null) {
      map['last_reviewed_at'] = Variable<DateTime>(lastReviewedAt);
    }
    map['acquisition'] = Variable<String>(acquisition);
    map['learning_step'] = Variable<int>(learningStep);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  TermProgressCompanion toCompanion(bool nullToAbsent) {
    return TermProgressCompanion(
      termId: Value(termId),
      state: Value(state),
      easeFactor: Value(easeFactor),
      intervalDays: Value(intervalDays),
      dueAt: dueAt == null && nullToAbsent
          ? const Value.absent()
          : Value(dueAt),
      reps: Value(reps),
      lapses: Value(lapses),
      lastReviewedAt: lastReviewedAt == null && nullToAbsent
          ? const Value.absent()
          : Value(lastReviewedAt),
      acquisition: Value(acquisition),
      learningStep: Value(learningStep),
      updatedAt: Value(updatedAt),
    );
  }

  factory TermProgressData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return TermProgressData(
      termId: serializer.fromJson<String>(json['termId']),
      state: serializer.fromJson<String>(json['state']),
      easeFactor: serializer.fromJson<double>(json['easeFactor']),
      intervalDays: serializer.fromJson<int>(json['intervalDays']),
      dueAt: serializer.fromJson<DateTime?>(json['dueAt']),
      reps: serializer.fromJson<int>(json['reps']),
      lapses: serializer.fromJson<int>(json['lapses']),
      lastReviewedAt: serializer.fromJson<DateTime?>(json['lastReviewedAt']),
      acquisition: serializer.fromJson<String>(json['acquisition']),
      learningStep: serializer.fromJson<int>(json['learningStep']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'termId': serializer.toJson<String>(termId),
      'state': serializer.toJson<String>(state),
      'easeFactor': serializer.toJson<double>(easeFactor),
      'intervalDays': serializer.toJson<int>(intervalDays),
      'dueAt': serializer.toJson<DateTime?>(dueAt),
      'reps': serializer.toJson<int>(reps),
      'lapses': serializer.toJson<int>(lapses),
      'lastReviewedAt': serializer.toJson<DateTime?>(lastReviewedAt),
      'acquisition': serializer.toJson<String>(acquisition),
      'learningStep': serializer.toJson<int>(learningStep),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  TermProgressData copyWith({
    String? termId,
    String? state,
    double? easeFactor,
    int? intervalDays,
    Value<DateTime?> dueAt = const Value.absent(),
    int? reps,
    int? lapses,
    Value<DateTime?> lastReviewedAt = const Value.absent(),
    String? acquisition,
    int? learningStep,
    DateTime? updatedAt,
  }) => TermProgressData(
    termId: termId ?? this.termId,
    state: state ?? this.state,
    easeFactor: easeFactor ?? this.easeFactor,
    intervalDays: intervalDays ?? this.intervalDays,
    dueAt: dueAt.present ? dueAt.value : this.dueAt,
    reps: reps ?? this.reps,
    lapses: lapses ?? this.lapses,
    lastReviewedAt: lastReviewedAt.present
        ? lastReviewedAt.value
        : this.lastReviewedAt,
    acquisition: acquisition ?? this.acquisition,
    learningStep: learningStep ?? this.learningStep,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  TermProgressData copyWithCompanion(TermProgressCompanion data) {
    return TermProgressData(
      termId: data.termId.present ? data.termId.value : this.termId,
      state: data.state.present ? data.state.value : this.state,
      easeFactor: data.easeFactor.present
          ? data.easeFactor.value
          : this.easeFactor,
      intervalDays: data.intervalDays.present
          ? data.intervalDays.value
          : this.intervalDays,
      dueAt: data.dueAt.present ? data.dueAt.value : this.dueAt,
      reps: data.reps.present ? data.reps.value : this.reps,
      lapses: data.lapses.present ? data.lapses.value : this.lapses,
      lastReviewedAt: data.lastReviewedAt.present
          ? data.lastReviewedAt.value
          : this.lastReviewedAt,
      acquisition: data.acquisition.present
          ? data.acquisition.value
          : this.acquisition,
      learningStep: data.learningStep.present
          ? data.learningStep.value
          : this.learningStep,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('TermProgressData(')
          ..write('termId: $termId, ')
          ..write('state: $state, ')
          ..write('easeFactor: $easeFactor, ')
          ..write('intervalDays: $intervalDays, ')
          ..write('dueAt: $dueAt, ')
          ..write('reps: $reps, ')
          ..write('lapses: $lapses, ')
          ..write('lastReviewedAt: $lastReviewedAt, ')
          ..write('acquisition: $acquisition, ')
          ..write('learningStep: $learningStep, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    termId,
    state,
    easeFactor,
    intervalDays,
    dueAt,
    reps,
    lapses,
    lastReviewedAt,
    acquisition,
    learningStep,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is TermProgressData &&
          other.termId == this.termId &&
          other.state == this.state &&
          other.easeFactor == this.easeFactor &&
          other.intervalDays == this.intervalDays &&
          other.dueAt == this.dueAt &&
          other.reps == this.reps &&
          other.lapses == this.lapses &&
          other.lastReviewedAt == this.lastReviewedAt &&
          other.acquisition == this.acquisition &&
          other.learningStep == this.learningStep &&
          other.updatedAt == this.updatedAt);
}

class TermProgressCompanion extends UpdateCompanion<TermProgressData> {
  final Value<String> termId;
  final Value<String> state;
  final Value<double> easeFactor;
  final Value<int> intervalDays;
  final Value<DateTime?> dueAt;
  final Value<int> reps;
  final Value<int> lapses;
  final Value<DateTime?> lastReviewedAt;
  final Value<String> acquisition;
  final Value<int> learningStep;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const TermProgressCompanion({
    this.termId = const Value.absent(),
    this.state = const Value.absent(),
    this.easeFactor = const Value.absent(),
    this.intervalDays = const Value.absent(),
    this.dueAt = const Value.absent(),
    this.reps = const Value.absent(),
    this.lapses = const Value.absent(),
    this.lastReviewedAt = const Value.absent(),
    this.acquisition = const Value.absent(),
    this.learningStep = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  TermProgressCompanion.insert({
    required String termId,
    this.state = const Value.absent(),
    this.easeFactor = const Value.absent(),
    this.intervalDays = const Value.absent(),
    this.dueAt = const Value.absent(),
    this.reps = const Value.absent(),
    this.lapses = const Value.absent(),
    this.lastReviewedAt = const Value.absent(),
    this.acquisition = const Value.absent(),
    this.learningStep = const Value.absent(),
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : termId = Value(termId),
       updatedAt = Value(updatedAt);
  static Insertable<TermProgressData> custom({
    Expression<String>? termId,
    Expression<String>? state,
    Expression<double>? easeFactor,
    Expression<int>? intervalDays,
    Expression<DateTime>? dueAt,
    Expression<int>? reps,
    Expression<int>? lapses,
    Expression<DateTime>? lastReviewedAt,
    Expression<String>? acquisition,
    Expression<int>? learningStep,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (termId != null) 'term_id': termId,
      if (state != null) 'state': state,
      if (easeFactor != null) 'ease_factor': easeFactor,
      if (intervalDays != null) 'interval_days': intervalDays,
      if (dueAt != null) 'due_at': dueAt,
      if (reps != null) 'reps': reps,
      if (lapses != null) 'lapses': lapses,
      if (lastReviewedAt != null) 'last_reviewed_at': lastReviewedAt,
      if (acquisition != null) 'acquisition': acquisition,
      if (learningStep != null) 'learning_step': learningStep,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  TermProgressCompanion copyWith({
    Value<String>? termId,
    Value<String>? state,
    Value<double>? easeFactor,
    Value<int>? intervalDays,
    Value<DateTime?>? dueAt,
    Value<int>? reps,
    Value<int>? lapses,
    Value<DateTime?>? lastReviewedAt,
    Value<String>? acquisition,
    Value<int>? learningStep,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return TermProgressCompanion(
      termId: termId ?? this.termId,
      state: state ?? this.state,
      easeFactor: easeFactor ?? this.easeFactor,
      intervalDays: intervalDays ?? this.intervalDays,
      dueAt: dueAt ?? this.dueAt,
      reps: reps ?? this.reps,
      lapses: lapses ?? this.lapses,
      lastReviewedAt: lastReviewedAt ?? this.lastReviewedAt,
      acquisition: acquisition ?? this.acquisition,
      learningStep: learningStep ?? this.learningStep,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (termId.present) {
      map['term_id'] = Variable<String>(termId.value);
    }
    if (state.present) {
      map['state'] = Variable<String>(state.value);
    }
    if (easeFactor.present) {
      map['ease_factor'] = Variable<double>(easeFactor.value);
    }
    if (intervalDays.present) {
      map['interval_days'] = Variable<int>(intervalDays.value);
    }
    if (dueAt.present) {
      map['due_at'] = Variable<DateTime>(dueAt.value);
    }
    if (reps.present) {
      map['reps'] = Variable<int>(reps.value);
    }
    if (lapses.present) {
      map['lapses'] = Variable<int>(lapses.value);
    }
    if (lastReviewedAt.present) {
      map['last_reviewed_at'] = Variable<DateTime>(lastReviewedAt.value);
    }
    if (acquisition.present) {
      map['acquisition'] = Variable<String>(acquisition.value);
    }
    if (learningStep.present) {
      map['learning_step'] = Variable<int>(learningStep.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('TermProgressCompanion(')
          ..write('termId: $termId, ')
          ..write('state: $state, ')
          ..write('easeFactor: $easeFactor, ')
          ..write('intervalDays: $intervalDays, ')
          ..write('dueAt: $dueAt, ')
          ..write('reps: $reps, ')
          ..write('lapses: $lapses, ')
          ..write('lastReviewedAt: $lastReviewedAt, ')
          ..write('acquisition: $acquisition, ')
          ..write('learningStep: $learningStep, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $SyncMetaTable extends SyncMeta
    with TableInfo<$SyncMetaTable, SyncMetaData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SyncMetaTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _keyMeta = const VerificationMeta('key');
  @override
  late final GeneratedColumn<String> key = GeneratedColumn<String>(
    'key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _valueMeta = const VerificationMeta('value');
  @override
  late final GeneratedColumn<String> value = GeneratedColumn<String>(
    'value',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [key, value];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'sync_meta';
  @override
  VerificationContext validateIntegrity(
    Insertable<SyncMetaData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('key')) {
      context.handle(
        _keyMeta,
        key.isAcceptableOrUnknown(data['key']!, _keyMeta),
      );
    } else if (isInserting) {
      context.missing(_keyMeta);
    }
    if (data.containsKey('value')) {
      context.handle(
        _valueMeta,
        value.isAcceptableOrUnknown(data['value']!, _valueMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {key};
  @override
  SyncMetaData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return SyncMetaData(
      key: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}key'],
      )!,
      value: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}value'],
      ),
    );
  }

  @override
  $SyncMetaTable createAlias(String alias) {
    return $SyncMetaTable(attachedDatabase, alias);
  }
}

class SyncMetaData extends DataClass implements Insertable<SyncMetaData> {
  final String key;
  final String? value;
  const SyncMetaData({required this.key, this.value});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['key'] = Variable<String>(key);
    if (!nullToAbsent || value != null) {
      map['value'] = Variable<String>(value);
    }
    return map;
  }

  SyncMetaCompanion toCompanion(bool nullToAbsent) {
    return SyncMetaCompanion(
      key: Value(key),
      value: value == null && nullToAbsent
          ? const Value.absent()
          : Value(value),
    );
  }

  factory SyncMetaData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return SyncMetaData(
      key: serializer.fromJson<String>(json['key']),
      value: serializer.fromJson<String?>(json['value']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'key': serializer.toJson<String>(key),
      'value': serializer.toJson<String?>(value),
    };
  }

  SyncMetaData copyWith({
    String? key,
    Value<String?> value = const Value.absent(),
  }) => SyncMetaData(
    key: key ?? this.key,
    value: value.present ? value.value : this.value,
  );
  SyncMetaData copyWithCompanion(SyncMetaCompanion data) {
    return SyncMetaData(
      key: data.key.present ? data.key.value : this.key,
      value: data.value.present ? data.value.value : this.value,
    );
  }

  @override
  String toString() {
    return (StringBuffer('SyncMetaData(')
          ..write('key: $key, ')
          ..write('value: $value')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(key, value);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is SyncMetaData &&
          other.key == this.key &&
          other.value == this.value);
}

class SyncMetaCompanion extends UpdateCompanion<SyncMetaData> {
  final Value<String> key;
  final Value<String?> value;
  final Value<int> rowid;
  const SyncMetaCompanion({
    this.key = const Value.absent(),
    this.value = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  SyncMetaCompanion.insert({
    required String key,
    this.value = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : key = Value(key);
  static Insertable<SyncMetaData> custom({
    Expression<String>? key,
    Expression<String>? value,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (key != null) 'key': key,
      if (value != null) 'value': value,
      if (rowid != null) 'rowid': rowid,
    });
  }

  SyncMetaCompanion copyWith({
    Value<String>? key,
    Value<String?>? value,
    Value<int>? rowid,
  }) {
    return SyncMetaCompanion(
      key: key ?? this.key,
      value: value ?? this.value,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (key.present) {
      map['key'] = Variable<String>(key.value);
    }
    if (value.present) {
      map['value'] = Variable<String>(value.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SyncMetaCompanion(')
          ..write('key: $key, ')
          ..write('value: $value, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $TriagedTermsTable extends TriagedTerms
    with TableInfo<$TriagedTermsTable, TriagedTerm> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $TriagedTermsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _termIdMeta = const VerificationMeta('termId');
  @override
  late final GeneratedColumn<String> termId = GeneratedColumn<String>(
    'term_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _collectionIdMeta = const VerificationMeta(
    'collectionId',
  );
  @override
  late final GeneratedColumn<String> collectionId = GeneratedColumn<String>(
    'collection_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _decidedAtMeta = const VerificationMeta(
    'decidedAt',
  );
  @override
  late final GeneratedColumn<DateTime> decidedAt = GeneratedColumn<DateTime>(
    'decided_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [termId, collectionId, decidedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'triaged_terms';
  @override
  VerificationContext validateIntegrity(
    Insertable<TriagedTerm> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('term_id')) {
      context.handle(
        _termIdMeta,
        termId.isAcceptableOrUnknown(data['term_id']!, _termIdMeta),
      );
    } else if (isInserting) {
      context.missing(_termIdMeta);
    }
    if (data.containsKey('collection_id')) {
      context.handle(
        _collectionIdMeta,
        collectionId.isAcceptableOrUnknown(
          data['collection_id']!,
          _collectionIdMeta,
        ),
      );
    }
    if (data.containsKey('decided_at')) {
      context.handle(
        _decidedAtMeta,
        decidedAt.isAcceptableOrUnknown(data['decided_at']!, _decidedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_decidedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {termId};
  @override
  TriagedTerm map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return TriagedTerm(
      termId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}term_id'],
      )!,
      collectionId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}collection_id'],
      ),
      decidedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}decided_at'],
      )!,
    );
  }

  @override
  $TriagedTermsTable createAlias(String alias) {
    return $TriagedTermsTable(attachedDatabase, alias);
  }
}

class TriagedTerm extends DataClass implements Insertable<TriagedTerm> {
  final String termId;
  final String? collectionId;
  final DateTime decidedAt;
  const TriagedTerm({
    required this.termId,
    this.collectionId,
    required this.decidedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['term_id'] = Variable<String>(termId);
    if (!nullToAbsent || collectionId != null) {
      map['collection_id'] = Variable<String>(collectionId);
    }
    map['decided_at'] = Variable<DateTime>(decidedAt);
    return map;
  }

  TriagedTermsCompanion toCompanion(bool nullToAbsent) {
    return TriagedTermsCompanion(
      termId: Value(termId),
      collectionId: collectionId == null && nullToAbsent
          ? const Value.absent()
          : Value(collectionId),
      decidedAt: Value(decidedAt),
    );
  }

  factory TriagedTerm.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return TriagedTerm(
      termId: serializer.fromJson<String>(json['termId']),
      collectionId: serializer.fromJson<String?>(json['collectionId']),
      decidedAt: serializer.fromJson<DateTime>(json['decidedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'termId': serializer.toJson<String>(termId),
      'collectionId': serializer.toJson<String?>(collectionId),
      'decidedAt': serializer.toJson<DateTime>(decidedAt),
    };
  }

  TriagedTerm copyWith({
    String? termId,
    Value<String?> collectionId = const Value.absent(),
    DateTime? decidedAt,
  }) => TriagedTerm(
    termId: termId ?? this.termId,
    collectionId: collectionId.present ? collectionId.value : this.collectionId,
    decidedAt: decidedAt ?? this.decidedAt,
  );
  TriagedTerm copyWithCompanion(TriagedTermsCompanion data) {
    return TriagedTerm(
      termId: data.termId.present ? data.termId.value : this.termId,
      collectionId: data.collectionId.present
          ? data.collectionId.value
          : this.collectionId,
      decidedAt: data.decidedAt.present ? data.decidedAt.value : this.decidedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('TriagedTerm(')
          ..write('termId: $termId, ')
          ..write('collectionId: $collectionId, ')
          ..write('decidedAt: $decidedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(termId, collectionId, decidedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is TriagedTerm &&
          other.termId == this.termId &&
          other.collectionId == this.collectionId &&
          other.decidedAt == this.decidedAt);
}

class TriagedTermsCompanion extends UpdateCompanion<TriagedTerm> {
  final Value<String> termId;
  final Value<String?> collectionId;
  final Value<DateTime> decidedAt;
  final Value<int> rowid;
  const TriagedTermsCompanion({
    this.termId = const Value.absent(),
    this.collectionId = const Value.absent(),
    this.decidedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  TriagedTermsCompanion.insert({
    required String termId,
    this.collectionId = const Value.absent(),
    required DateTime decidedAt,
    this.rowid = const Value.absent(),
  }) : termId = Value(termId),
       decidedAt = Value(decidedAt);
  static Insertable<TriagedTerm> custom({
    Expression<String>? termId,
    Expression<String>? collectionId,
    Expression<DateTime>? decidedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (termId != null) 'term_id': termId,
      if (collectionId != null) 'collection_id': collectionId,
      if (decidedAt != null) 'decided_at': decidedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  TriagedTermsCompanion copyWith({
    Value<String>? termId,
    Value<String?>? collectionId,
    Value<DateTime>? decidedAt,
    Value<int>? rowid,
  }) {
    return TriagedTermsCompanion(
      termId: termId ?? this.termId,
      collectionId: collectionId ?? this.collectionId,
      decidedAt: decidedAt ?? this.decidedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (termId.present) {
      map['term_id'] = Variable<String>(termId.value);
    }
    if (collectionId.present) {
      map['collection_id'] = Variable<String>(collectionId.value);
    }
    if (decidedAt.present) {
      map['decided_at'] = Variable<DateTime>(decidedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('TriagedTermsCompanion(')
          ..write('termId: $termId, ')
          ..write('collectionId: $collectionId, ')
          ..write('decidedAt: $decidedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $PendingGenerationsTable extends PendingGenerations
    with TableInfo<$PendingGenerationsTable, PendingGeneration> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PendingGenerationsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _topicMeta = const VerificationMeta('topic');
  @override
  late final GeneratedColumn<String> topic = GeneratedColumn<String>(
    'topic',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );
  static const VerificationMeta _collectionIdMeta = const VerificationMeta(
    'collectionId',
  );
  @override
  late final GeneratedColumn<String> collectionId = GeneratedColumn<String>(
    'collection_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _errorMeta = const VerificationMeta('error');
  @override
  late final GeneratedColumn<String> error = GeneratedColumn<String>(
    'error',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _requestedMeta = const VerificationMeta(
    'requested',
  );
  @override
  late final GeneratedColumn<int> requested = GeneratedColumn<int>(
    'requested',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _deliveredMeta = const VerificationMeta(
    'delivered',
  );
  @override
  late final GeneratedColumn<int> delivered = GeneratedColumn<int>(
    'delivered',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _sourceLangMeta = const VerificationMeta(
    'sourceLang',
  );
  @override
  late final GeneratedColumn<String> sourceLang = GeneratedColumn<String>(
    'source_lang',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('ru'),
  );
  static const VerificationMeta _targetLangMeta = const VerificationMeta(
    'targetLang',
  );
  @override
  late final GeneratedColumn<String> targetLang = GeneratedColumn<String>(
    'target_lang',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('en'),
  );
  static const VerificationMeta _levelsCsvMeta = const VerificationMeta(
    'levelsCsv',
  );
  @override
  late final GeneratedColumn<String> levelsCsv = GeneratedColumn<String>(
    'levels_csv',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('A2,B1'),
  );
  static const VerificationMeta _sizeMeta = const VerificationMeta('size');
  @override
  late final GeneratedColumn<int> size = GeneratedColumn<int>(
    'size',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(15),
  );
  static const VerificationMeta _sentMeta = const VerificationMeta('sent');
  @override
  late final GeneratedColumn<bool> sent = GeneratedColumn<bool>(
    'sent',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("sent" IN (0, 1))',
    ),
    defaultValue: const Constant(true),
  );
  static const VerificationMeta _targetLangExplicitMeta =
      const VerificationMeta('targetLangExplicit');
  @override
  late final GeneratedColumn<bool> targetLangExplicit = GeneratedColumn<bool>(
    'target_lang_explicit',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("target_lang_explicit" IN (0, 1))',
    ),
    defaultValue: const Constant(true),
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    topic,
    status,
    collectionId,
    error,
    requested,
    delivered,
    sourceLang,
    targetLang,
    levelsCsv,
    size,
    sent,
    targetLangExplicit,
    createdAt,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'pending_generations';
  @override
  VerificationContext validateIntegrity(
    Insertable<PendingGeneration> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('topic')) {
      context.handle(
        _topicMeta,
        topic.isAcceptableOrUnknown(data['topic']!, _topicMeta),
      );
    } else if (isInserting) {
      context.missing(_topicMeta);
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('collection_id')) {
      context.handle(
        _collectionIdMeta,
        collectionId.isAcceptableOrUnknown(
          data['collection_id']!,
          _collectionIdMeta,
        ),
      );
    }
    if (data.containsKey('error')) {
      context.handle(
        _errorMeta,
        error.isAcceptableOrUnknown(data['error']!, _errorMeta),
      );
    }
    if (data.containsKey('requested')) {
      context.handle(
        _requestedMeta,
        requested.isAcceptableOrUnknown(data['requested']!, _requestedMeta),
      );
    }
    if (data.containsKey('delivered')) {
      context.handle(
        _deliveredMeta,
        delivered.isAcceptableOrUnknown(data['delivered']!, _deliveredMeta),
      );
    }
    if (data.containsKey('source_lang')) {
      context.handle(
        _sourceLangMeta,
        sourceLang.isAcceptableOrUnknown(data['source_lang']!, _sourceLangMeta),
      );
    }
    if (data.containsKey('target_lang')) {
      context.handle(
        _targetLangMeta,
        targetLang.isAcceptableOrUnknown(data['target_lang']!, _targetLangMeta),
      );
    }
    if (data.containsKey('levels_csv')) {
      context.handle(
        _levelsCsvMeta,
        levelsCsv.isAcceptableOrUnknown(data['levels_csv']!, _levelsCsvMeta),
      );
    }
    if (data.containsKey('size')) {
      context.handle(
        _sizeMeta,
        size.isAcceptableOrUnknown(data['size']!, _sizeMeta),
      );
    }
    if (data.containsKey('sent')) {
      context.handle(
        _sentMeta,
        sent.isAcceptableOrUnknown(data['sent']!, _sentMeta),
      );
    }
    if (data.containsKey('target_lang_explicit')) {
      context.handle(
        _targetLangExplicitMeta,
        targetLangExplicit.isAcceptableOrUnknown(
          data['target_lang_explicit']!,
          _targetLangExplicitMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  PendingGeneration map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PendingGeneration(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      topic: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}topic'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      collectionId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}collection_id'],
      ),
      error: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}error'],
      ),
      requested: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}requested'],
      ),
      delivered: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}delivered'],
      ),
      sourceLang: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}source_lang'],
      )!,
      targetLang: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}target_lang'],
      )!,
      levelsCsv: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}levels_csv'],
      )!,
      size: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}size'],
      )!,
      sent: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}sent'],
      )!,
      targetLangExplicit: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}target_lang_explicit'],
      )!,
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $PendingGenerationsTable createAlias(String alias) {
    return $PendingGenerationsTable(attachedDatabase, alias);
  }
}

class PendingGeneration extends DataClass
    implements Insertable<PendingGeneration> {
  final String id;
  final String topic;
  final String status;
  final String? collectionId;
  final String? error;
  final int? requested;
  final int? delivered;
  final String sourceLang;
  final String targetLang;
  final String levelsCsv;
  final int size;
  final bool sent;
  final bool targetLangExplicit;
  final DateTime createdAt;
  final DateTime updatedAt;
  const PendingGeneration({
    required this.id,
    required this.topic,
    required this.status,
    this.collectionId,
    this.error,
    this.requested,
    this.delivered,
    required this.sourceLang,
    required this.targetLang,
    required this.levelsCsv,
    required this.size,
    required this.sent,
    required this.targetLangExplicit,
    required this.createdAt,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['topic'] = Variable<String>(topic);
    map['status'] = Variable<String>(status);
    if (!nullToAbsent || collectionId != null) {
      map['collection_id'] = Variable<String>(collectionId);
    }
    if (!nullToAbsent || error != null) {
      map['error'] = Variable<String>(error);
    }
    if (!nullToAbsent || requested != null) {
      map['requested'] = Variable<int>(requested);
    }
    if (!nullToAbsent || delivered != null) {
      map['delivered'] = Variable<int>(delivered);
    }
    map['source_lang'] = Variable<String>(sourceLang);
    map['target_lang'] = Variable<String>(targetLang);
    map['levels_csv'] = Variable<String>(levelsCsv);
    map['size'] = Variable<int>(size);
    map['sent'] = Variable<bool>(sent);
    map['target_lang_explicit'] = Variable<bool>(targetLangExplicit);
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  PendingGenerationsCompanion toCompanion(bool nullToAbsent) {
    return PendingGenerationsCompanion(
      id: Value(id),
      topic: Value(topic),
      status: Value(status),
      collectionId: collectionId == null && nullToAbsent
          ? const Value.absent()
          : Value(collectionId),
      error: error == null && nullToAbsent
          ? const Value.absent()
          : Value(error),
      requested: requested == null && nullToAbsent
          ? const Value.absent()
          : Value(requested),
      delivered: delivered == null && nullToAbsent
          ? const Value.absent()
          : Value(delivered),
      sourceLang: Value(sourceLang),
      targetLang: Value(targetLang),
      levelsCsv: Value(levelsCsv),
      size: Value(size),
      sent: Value(sent),
      targetLangExplicit: Value(targetLangExplicit),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory PendingGeneration.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PendingGeneration(
      id: serializer.fromJson<String>(json['id']),
      topic: serializer.fromJson<String>(json['topic']),
      status: serializer.fromJson<String>(json['status']),
      collectionId: serializer.fromJson<String?>(json['collectionId']),
      error: serializer.fromJson<String?>(json['error']),
      requested: serializer.fromJson<int?>(json['requested']),
      delivered: serializer.fromJson<int?>(json['delivered']),
      sourceLang: serializer.fromJson<String>(json['sourceLang']),
      targetLang: serializer.fromJson<String>(json['targetLang']),
      levelsCsv: serializer.fromJson<String>(json['levelsCsv']),
      size: serializer.fromJson<int>(json['size']),
      sent: serializer.fromJson<bool>(json['sent']),
      targetLangExplicit: serializer.fromJson<bool>(json['targetLangExplicit']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'topic': serializer.toJson<String>(topic),
      'status': serializer.toJson<String>(status),
      'collectionId': serializer.toJson<String?>(collectionId),
      'error': serializer.toJson<String?>(error),
      'requested': serializer.toJson<int?>(requested),
      'delivered': serializer.toJson<int?>(delivered),
      'sourceLang': serializer.toJson<String>(sourceLang),
      'targetLang': serializer.toJson<String>(targetLang),
      'levelsCsv': serializer.toJson<String>(levelsCsv),
      'size': serializer.toJson<int>(size),
      'sent': serializer.toJson<bool>(sent),
      'targetLangExplicit': serializer.toJson<bool>(targetLangExplicit),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  PendingGeneration copyWith({
    String? id,
    String? topic,
    String? status,
    Value<String?> collectionId = const Value.absent(),
    Value<String?> error = const Value.absent(),
    Value<int?> requested = const Value.absent(),
    Value<int?> delivered = const Value.absent(),
    String? sourceLang,
    String? targetLang,
    String? levelsCsv,
    int? size,
    bool? sent,
    bool? targetLangExplicit,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => PendingGeneration(
    id: id ?? this.id,
    topic: topic ?? this.topic,
    status: status ?? this.status,
    collectionId: collectionId.present ? collectionId.value : this.collectionId,
    error: error.present ? error.value : this.error,
    requested: requested.present ? requested.value : this.requested,
    delivered: delivered.present ? delivered.value : this.delivered,
    sourceLang: sourceLang ?? this.sourceLang,
    targetLang: targetLang ?? this.targetLang,
    levelsCsv: levelsCsv ?? this.levelsCsv,
    size: size ?? this.size,
    sent: sent ?? this.sent,
    targetLangExplicit: targetLangExplicit ?? this.targetLangExplicit,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  PendingGeneration copyWithCompanion(PendingGenerationsCompanion data) {
    return PendingGeneration(
      id: data.id.present ? data.id.value : this.id,
      topic: data.topic.present ? data.topic.value : this.topic,
      status: data.status.present ? data.status.value : this.status,
      collectionId: data.collectionId.present
          ? data.collectionId.value
          : this.collectionId,
      error: data.error.present ? data.error.value : this.error,
      requested: data.requested.present ? data.requested.value : this.requested,
      delivered: data.delivered.present ? data.delivered.value : this.delivered,
      sourceLang: data.sourceLang.present
          ? data.sourceLang.value
          : this.sourceLang,
      targetLang: data.targetLang.present
          ? data.targetLang.value
          : this.targetLang,
      levelsCsv: data.levelsCsv.present ? data.levelsCsv.value : this.levelsCsv,
      size: data.size.present ? data.size.value : this.size,
      sent: data.sent.present ? data.sent.value : this.sent,
      targetLangExplicit: data.targetLangExplicit.present
          ? data.targetLangExplicit.value
          : this.targetLangExplicit,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PendingGeneration(')
          ..write('id: $id, ')
          ..write('topic: $topic, ')
          ..write('status: $status, ')
          ..write('collectionId: $collectionId, ')
          ..write('error: $error, ')
          ..write('requested: $requested, ')
          ..write('delivered: $delivered, ')
          ..write('sourceLang: $sourceLang, ')
          ..write('targetLang: $targetLang, ')
          ..write('levelsCsv: $levelsCsv, ')
          ..write('size: $size, ')
          ..write('sent: $sent, ')
          ..write('targetLangExplicit: $targetLangExplicit, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    topic,
    status,
    collectionId,
    error,
    requested,
    delivered,
    sourceLang,
    targetLang,
    levelsCsv,
    size,
    sent,
    targetLangExplicit,
    createdAt,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PendingGeneration &&
          other.id == this.id &&
          other.topic == this.topic &&
          other.status == this.status &&
          other.collectionId == this.collectionId &&
          other.error == this.error &&
          other.requested == this.requested &&
          other.delivered == this.delivered &&
          other.sourceLang == this.sourceLang &&
          other.targetLang == this.targetLang &&
          other.levelsCsv == this.levelsCsv &&
          other.size == this.size &&
          other.sent == this.sent &&
          other.targetLangExplicit == this.targetLangExplicit &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class PendingGenerationsCompanion extends UpdateCompanion<PendingGeneration> {
  final Value<String> id;
  final Value<String> topic;
  final Value<String> status;
  final Value<String?> collectionId;
  final Value<String?> error;
  final Value<int?> requested;
  final Value<int?> delivered;
  final Value<String> sourceLang;
  final Value<String> targetLang;
  final Value<String> levelsCsv;
  final Value<int> size;
  final Value<bool> sent;
  final Value<bool> targetLangExplicit;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const PendingGenerationsCompanion({
    this.id = const Value.absent(),
    this.topic = const Value.absent(),
    this.status = const Value.absent(),
    this.collectionId = const Value.absent(),
    this.error = const Value.absent(),
    this.requested = const Value.absent(),
    this.delivered = const Value.absent(),
    this.sourceLang = const Value.absent(),
    this.targetLang = const Value.absent(),
    this.levelsCsv = const Value.absent(),
    this.size = const Value.absent(),
    this.sent = const Value.absent(),
    this.targetLangExplicit = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PendingGenerationsCompanion.insert({
    required String id,
    required String topic,
    this.status = const Value.absent(),
    this.collectionId = const Value.absent(),
    this.error = const Value.absent(),
    this.requested = const Value.absent(),
    this.delivered = const Value.absent(),
    this.sourceLang = const Value.absent(),
    this.targetLang = const Value.absent(),
    this.levelsCsv = const Value.absent(),
    this.size = const Value.absent(),
    this.sent = const Value.absent(),
    this.targetLangExplicit = const Value.absent(),
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : id = Value(id),
       topic = Value(topic),
       createdAt = Value(createdAt),
       updatedAt = Value(updatedAt);
  static Insertable<PendingGeneration> custom({
    Expression<String>? id,
    Expression<String>? topic,
    Expression<String>? status,
    Expression<String>? collectionId,
    Expression<String>? error,
    Expression<int>? requested,
    Expression<int>? delivered,
    Expression<String>? sourceLang,
    Expression<String>? targetLang,
    Expression<String>? levelsCsv,
    Expression<int>? size,
    Expression<bool>? sent,
    Expression<bool>? targetLangExplicit,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (topic != null) 'topic': topic,
      if (status != null) 'status': status,
      if (collectionId != null) 'collection_id': collectionId,
      if (error != null) 'error': error,
      if (requested != null) 'requested': requested,
      if (delivered != null) 'delivered': delivered,
      if (sourceLang != null) 'source_lang': sourceLang,
      if (targetLang != null) 'target_lang': targetLang,
      if (levelsCsv != null) 'levels_csv': levelsCsv,
      if (size != null) 'size': size,
      if (sent != null) 'sent': sent,
      if (targetLangExplicit != null)
        'target_lang_explicit': targetLangExplicit,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PendingGenerationsCompanion copyWith({
    Value<String>? id,
    Value<String>? topic,
    Value<String>? status,
    Value<String?>? collectionId,
    Value<String?>? error,
    Value<int?>? requested,
    Value<int?>? delivered,
    Value<String>? sourceLang,
    Value<String>? targetLang,
    Value<String>? levelsCsv,
    Value<int>? size,
    Value<bool>? sent,
    Value<bool>? targetLangExplicit,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return PendingGenerationsCompanion(
      id: id ?? this.id,
      topic: topic ?? this.topic,
      status: status ?? this.status,
      collectionId: collectionId ?? this.collectionId,
      error: error ?? this.error,
      requested: requested ?? this.requested,
      delivered: delivered ?? this.delivered,
      sourceLang: sourceLang ?? this.sourceLang,
      targetLang: targetLang ?? this.targetLang,
      levelsCsv: levelsCsv ?? this.levelsCsv,
      size: size ?? this.size,
      sent: sent ?? this.sent,
      targetLangExplicit: targetLangExplicit ?? this.targetLangExplicit,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (topic.present) {
      map['topic'] = Variable<String>(topic.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (collectionId.present) {
      map['collection_id'] = Variable<String>(collectionId.value);
    }
    if (error.present) {
      map['error'] = Variable<String>(error.value);
    }
    if (requested.present) {
      map['requested'] = Variable<int>(requested.value);
    }
    if (delivered.present) {
      map['delivered'] = Variable<int>(delivered.value);
    }
    if (sourceLang.present) {
      map['source_lang'] = Variable<String>(sourceLang.value);
    }
    if (targetLang.present) {
      map['target_lang'] = Variable<String>(targetLang.value);
    }
    if (levelsCsv.present) {
      map['levels_csv'] = Variable<String>(levelsCsv.value);
    }
    if (size.present) {
      map['size'] = Variable<int>(size.value);
    }
    if (sent.present) {
      map['sent'] = Variable<bool>(sent.value);
    }
    if (targetLangExplicit.present) {
      map['target_lang_explicit'] = Variable<bool>(targetLangExplicit.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PendingGenerationsCompanion(')
          ..write('id: $id, ')
          ..write('topic: $topic, ')
          ..write('status: $status, ')
          ..write('collectionId: $collectionId, ')
          ..write('error: $error, ')
          ..write('requested: $requested, ')
          ..write('delivered: $delivered, ')
          ..write('sourceLang: $sourceLang, ')
          ..write('targetLang: $targetLang, ')
          ..write('levelsCsv: $levelsCsv, ')
          ..write('size: $size, ')
          ..write('sent: $sent, ')
          ..write('targetLangExplicit: $targetLangExplicit, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $DailyActivityTable extends DailyActivity
    with TableInfo<$DailyActivityTable, DailyActivityData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $DailyActivityTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _dayMeta = const VerificationMeta('day');
  @override
  late final GeneratedColumn<String> day = GeneratedColumn<String>(
    'day',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _reviewsMeta = const VerificationMeta(
    'reviews',
  );
  @override
  late final GeneratedColumn<int> reviews = GeneratedColumn<int>(
    'reviews',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  @override
  List<GeneratedColumn> get $columns => [day, reviews];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'daily_activity';
  @override
  VerificationContext validateIntegrity(
    Insertable<DailyActivityData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('day')) {
      context.handle(
        _dayMeta,
        day.isAcceptableOrUnknown(data['day']!, _dayMeta),
      );
    } else if (isInserting) {
      context.missing(_dayMeta);
    }
    if (data.containsKey('reviews')) {
      context.handle(
        _reviewsMeta,
        reviews.isAcceptableOrUnknown(data['reviews']!, _reviewsMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {day};
  @override
  DailyActivityData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return DailyActivityData(
      day: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}day'],
      )!,
      reviews: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}reviews'],
      )!,
    );
  }

  @override
  $DailyActivityTable createAlias(String alias) {
    return $DailyActivityTable(attachedDatabase, alias);
  }
}

class DailyActivityData extends DataClass
    implements Insertable<DailyActivityData> {
  final String day;
  final int reviews;
  const DailyActivityData({required this.day, required this.reviews});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['day'] = Variable<String>(day);
    map['reviews'] = Variable<int>(reviews);
    return map;
  }

  DailyActivityCompanion toCompanion(bool nullToAbsent) {
    return DailyActivityCompanion(day: Value(day), reviews: Value(reviews));
  }

  factory DailyActivityData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return DailyActivityData(
      day: serializer.fromJson<String>(json['day']),
      reviews: serializer.fromJson<int>(json['reviews']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'day': serializer.toJson<String>(day),
      'reviews': serializer.toJson<int>(reviews),
    };
  }

  DailyActivityData copyWith({String? day, int? reviews}) =>
      DailyActivityData(day: day ?? this.day, reviews: reviews ?? this.reviews);
  DailyActivityData copyWithCompanion(DailyActivityCompanion data) {
    return DailyActivityData(
      day: data.day.present ? data.day.value : this.day,
      reviews: data.reviews.present ? data.reviews.value : this.reviews,
    );
  }

  @override
  String toString() {
    return (StringBuffer('DailyActivityData(')
          ..write('day: $day, ')
          ..write('reviews: $reviews')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(day, reviews);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is DailyActivityData &&
          other.day == this.day &&
          other.reviews == this.reviews);
}

class DailyActivityCompanion extends UpdateCompanion<DailyActivityData> {
  final Value<String> day;
  final Value<int> reviews;
  final Value<int> rowid;
  const DailyActivityCompanion({
    this.day = const Value.absent(),
    this.reviews = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  DailyActivityCompanion.insert({
    required String day,
    this.reviews = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : day = Value(day);
  static Insertable<DailyActivityData> custom({
    Expression<String>? day,
    Expression<int>? reviews,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (day != null) 'day': day,
      if (reviews != null) 'reviews': reviews,
      if (rowid != null) 'rowid': rowid,
    });
  }

  DailyActivityCompanion copyWith({
    Value<String>? day,
    Value<int>? reviews,
    Value<int>? rowid,
  }) {
    return DailyActivityCompanion(
      day: day ?? this.day,
      reviews: reviews ?? this.reviews,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (day.present) {
      map['day'] = Variable<String>(day.value);
    }
    if (reviews.present) {
      map['reviews'] = Variable<int>(reviews.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('DailyActivityCompanion(')
          ..write('day: $day, ')
          ..write('reviews: $reviews, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $ReviewQueueRowsTable extends ReviewQueueRows
    with TableInfo<$ReviewQueueRowsTable, ReviewQueueRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ReviewQueueRowsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _termIdMeta = const VerificationMeta('termId');
  @override
  late final GeneratedColumn<String> termId = GeneratedColumn<String>(
    'term_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _exerciseModeMeta = const VerificationMeta(
    'exerciseMode',
  );
  @override
  late final GeneratedColumn<String> exerciseMode = GeneratedColumn<String>(
    'exercise_mode',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _responseMeta = const VerificationMeta(
    'response',
  );
  @override
  late final GeneratedColumn<String> response = GeneratedColumn<String>(
    'response',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _clientSeqMeta = const VerificationMeta(
    'clientSeq',
  );
  @override
  late final GeneratedColumn<int> clientSeq = GeneratedColumn<int>(
    'client_seq',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _answeredAtMeta = const VerificationMeta(
    'answeredAt',
  );
  @override
  late final GeneratedColumn<String> answeredAt = GeneratedColumn<String>(
    'answered_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _usedHintMeta = const VerificationMeta(
    'usedHint',
  );
  @override
  late final GeneratedColumn<bool> usedHint = GeneratedColumn<bool>(
    'used_hint',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("used_hint" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _isPracticeMeta = const VerificationMeta(
    'isPractice',
  );
  @override
  late final GeneratedColumn<bool> isPractice = GeneratedColumn<bool>(
    'is_practice',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("is_practice" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _latencyMsMeta = const VerificationMeta(
    'latencyMs',
  );
  @override
  late final GeneratedColumn<int> latencyMs = GeneratedColumn<int>(
    'latency_ms',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _sessionIdMeta = const VerificationMeta(
    'sessionId',
  );
  @override
  late final GeneratedColumn<String> sessionId = GeneratedColumn<String>(
    'session_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _ladderStepMeta = const VerificationMeta(
    'ladderStep',
  );
  @override
  late final GeneratedColumn<int> ladderStep = GeneratedColumn<int>(
    'ladder_step',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    termId,
    exerciseMode,
    response,
    clientSeq,
    answeredAt,
    usedHint,
    isPractice,
    latencyMs,
    sessionId,
    ladderStep,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'review_queue_rows';
  @override
  VerificationContext validateIntegrity(
    Insertable<ReviewQueueRow> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('term_id')) {
      context.handle(
        _termIdMeta,
        termId.isAcceptableOrUnknown(data['term_id']!, _termIdMeta),
      );
    } else if (isInserting) {
      context.missing(_termIdMeta);
    }
    if (data.containsKey('exercise_mode')) {
      context.handle(
        _exerciseModeMeta,
        exerciseMode.isAcceptableOrUnknown(
          data['exercise_mode']!,
          _exerciseModeMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_exerciseModeMeta);
    }
    if (data.containsKey('response')) {
      context.handle(
        _responseMeta,
        response.isAcceptableOrUnknown(data['response']!, _responseMeta),
      );
    } else if (isInserting) {
      context.missing(_responseMeta);
    }
    if (data.containsKey('client_seq')) {
      context.handle(
        _clientSeqMeta,
        clientSeq.isAcceptableOrUnknown(data['client_seq']!, _clientSeqMeta),
      );
    } else if (isInserting) {
      context.missing(_clientSeqMeta);
    }
    if (data.containsKey('answered_at')) {
      context.handle(
        _answeredAtMeta,
        answeredAt.isAcceptableOrUnknown(data['answered_at']!, _answeredAtMeta),
      );
    } else if (isInserting) {
      context.missing(_answeredAtMeta);
    }
    if (data.containsKey('used_hint')) {
      context.handle(
        _usedHintMeta,
        usedHint.isAcceptableOrUnknown(data['used_hint']!, _usedHintMeta),
      );
    }
    if (data.containsKey('is_practice')) {
      context.handle(
        _isPracticeMeta,
        isPractice.isAcceptableOrUnknown(data['is_practice']!, _isPracticeMeta),
      );
    }
    if (data.containsKey('latency_ms')) {
      context.handle(
        _latencyMsMeta,
        latencyMs.isAcceptableOrUnknown(data['latency_ms']!, _latencyMsMeta),
      );
    }
    if (data.containsKey('session_id')) {
      context.handle(
        _sessionIdMeta,
        sessionId.isAcceptableOrUnknown(data['session_id']!, _sessionIdMeta),
      );
    }
    if (data.containsKey('ladder_step')) {
      context.handle(
        _ladderStepMeta,
        ladderStep.isAcceptableOrUnknown(data['ladder_step']!, _ladderStepMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  ReviewQueueRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return ReviewQueueRow(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      termId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}term_id'],
      )!,
      exerciseMode: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}exercise_mode'],
      )!,
      response: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}response'],
      )!,
      clientSeq: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}client_seq'],
      )!,
      answeredAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}answered_at'],
      )!,
      usedHint: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}used_hint'],
      )!,
      isPractice: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}is_practice'],
      )!,
      latencyMs: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}latency_ms'],
      ),
      sessionId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}session_id'],
      ),
      ladderStep: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ladder_step'],
      ),
    );
  }

  @override
  $ReviewQueueRowsTable createAlias(String alias) {
    return $ReviewQueueRowsTable(attachedDatabase, alias);
  }
}

class ReviewQueueRow extends DataClass implements Insertable<ReviewQueueRow> {
  final String id;
  final String termId;
  final String exerciseMode;
  final String response;
  final int clientSeq;
  final String answeredAt;
  final bool usedHint;
  final bool isPractice;
  final int? latencyMs;
  final String? sessionId;

  /// The rung the card was dealt at, echoed back with the answer (1–5; null off the ladder).
  /// Rung 1 is graded by IDENTITY server-side, and the server only takes that path when this says
  /// so — without it a tapped term id is graded as text against the term's own forms and a correct
  /// tap is folded as a lapse. Queued with the answer because the pair's rung MOVES as the answer
  /// is folded, so nothing else can still say what the card asked.
  final int? ladderStep;
  const ReviewQueueRow({
    required this.id,
    required this.termId,
    required this.exerciseMode,
    required this.response,
    required this.clientSeq,
    required this.answeredAt,
    required this.usedHint,
    required this.isPractice,
    this.latencyMs,
    this.sessionId,
    this.ladderStep,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['term_id'] = Variable<String>(termId);
    map['exercise_mode'] = Variable<String>(exerciseMode);
    map['response'] = Variable<String>(response);
    map['client_seq'] = Variable<int>(clientSeq);
    map['answered_at'] = Variable<String>(answeredAt);
    map['used_hint'] = Variable<bool>(usedHint);
    map['is_practice'] = Variable<bool>(isPractice);
    if (!nullToAbsent || latencyMs != null) {
      map['latency_ms'] = Variable<int>(latencyMs);
    }
    if (!nullToAbsent || sessionId != null) {
      map['session_id'] = Variable<String>(sessionId);
    }
    if (!nullToAbsent || ladderStep != null) {
      map['ladder_step'] = Variable<int>(ladderStep);
    }
    return map;
  }

  ReviewQueueRowsCompanion toCompanion(bool nullToAbsent) {
    return ReviewQueueRowsCompanion(
      id: Value(id),
      termId: Value(termId),
      exerciseMode: Value(exerciseMode),
      response: Value(response),
      clientSeq: Value(clientSeq),
      answeredAt: Value(answeredAt),
      usedHint: Value(usedHint),
      isPractice: Value(isPractice),
      latencyMs: latencyMs == null && nullToAbsent
          ? const Value.absent()
          : Value(latencyMs),
      sessionId: sessionId == null && nullToAbsent
          ? const Value.absent()
          : Value(sessionId),
      ladderStep: ladderStep == null && nullToAbsent
          ? const Value.absent()
          : Value(ladderStep),
    );
  }

  factory ReviewQueueRow.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return ReviewQueueRow(
      id: serializer.fromJson<String>(json['id']),
      termId: serializer.fromJson<String>(json['termId']),
      exerciseMode: serializer.fromJson<String>(json['exerciseMode']),
      response: serializer.fromJson<String>(json['response']),
      clientSeq: serializer.fromJson<int>(json['clientSeq']),
      answeredAt: serializer.fromJson<String>(json['answeredAt']),
      usedHint: serializer.fromJson<bool>(json['usedHint']),
      isPractice: serializer.fromJson<bool>(json['isPractice']),
      latencyMs: serializer.fromJson<int?>(json['latencyMs']),
      sessionId: serializer.fromJson<String?>(json['sessionId']),
      ladderStep: serializer.fromJson<int?>(json['ladderStep']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'termId': serializer.toJson<String>(termId),
      'exerciseMode': serializer.toJson<String>(exerciseMode),
      'response': serializer.toJson<String>(response),
      'clientSeq': serializer.toJson<int>(clientSeq),
      'answeredAt': serializer.toJson<String>(answeredAt),
      'usedHint': serializer.toJson<bool>(usedHint),
      'isPractice': serializer.toJson<bool>(isPractice),
      'latencyMs': serializer.toJson<int?>(latencyMs),
      'sessionId': serializer.toJson<String?>(sessionId),
      'ladderStep': serializer.toJson<int?>(ladderStep),
    };
  }

  ReviewQueueRow copyWith({
    String? id,
    String? termId,
    String? exerciseMode,
    String? response,
    int? clientSeq,
    String? answeredAt,
    bool? usedHint,
    bool? isPractice,
    Value<int?> latencyMs = const Value.absent(),
    Value<String?> sessionId = const Value.absent(),
    Value<int?> ladderStep = const Value.absent(),
  }) => ReviewQueueRow(
    id: id ?? this.id,
    termId: termId ?? this.termId,
    exerciseMode: exerciseMode ?? this.exerciseMode,
    response: response ?? this.response,
    clientSeq: clientSeq ?? this.clientSeq,
    answeredAt: answeredAt ?? this.answeredAt,
    usedHint: usedHint ?? this.usedHint,
    isPractice: isPractice ?? this.isPractice,
    latencyMs: latencyMs.present ? latencyMs.value : this.latencyMs,
    sessionId: sessionId.present ? sessionId.value : this.sessionId,
    ladderStep: ladderStep.present ? ladderStep.value : this.ladderStep,
  );
  ReviewQueueRow copyWithCompanion(ReviewQueueRowsCompanion data) {
    return ReviewQueueRow(
      id: data.id.present ? data.id.value : this.id,
      termId: data.termId.present ? data.termId.value : this.termId,
      exerciseMode: data.exerciseMode.present
          ? data.exerciseMode.value
          : this.exerciseMode,
      response: data.response.present ? data.response.value : this.response,
      clientSeq: data.clientSeq.present ? data.clientSeq.value : this.clientSeq,
      answeredAt: data.answeredAt.present
          ? data.answeredAt.value
          : this.answeredAt,
      usedHint: data.usedHint.present ? data.usedHint.value : this.usedHint,
      isPractice: data.isPractice.present
          ? data.isPractice.value
          : this.isPractice,
      latencyMs: data.latencyMs.present ? data.latencyMs.value : this.latencyMs,
      sessionId: data.sessionId.present ? data.sessionId.value : this.sessionId,
      ladderStep: data.ladderStep.present
          ? data.ladderStep.value
          : this.ladderStep,
    );
  }

  @override
  String toString() {
    return (StringBuffer('ReviewQueueRow(')
          ..write('id: $id, ')
          ..write('termId: $termId, ')
          ..write('exerciseMode: $exerciseMode, ')
          ..write('response: $response, ')
          ..write('clientSeq: $clientSeq, ')
          ..write('answeredAt: $answeredAt, ')
          ..write('usedHint: $usedHint, ')
          ..write('isPractice: $isPractice, ')
          ..write('latencyMs: $latencyMs, ')
          ..write('sessionId: $sessionId, ')
          ..write('ladderStep: $ladderStep')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    termId,
    exerciseMode,
    response,
    clientSeq,
    answeredAt,
    usedHint,
    isPractice,
    latencyMs,
    sessionId,
    ladderStep,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ReviewQueueRow &&
          other.id == this.id &&
          other.termId == this.termId &&
          other.exerciseMode == this.exerciseMode &&
          other.response == this.response &&
          other.clientSeq == this.clientSeq &&
          other.answeredAt == this.answeredAt &&
          other.usedHint == this.usedHint &&
          other.isPractice == this.isPractice &&
          other.latencyMs == this.latencyMs &&
          other.sessionId == this.sessionId &&
          other.ladderStep == this.ladderStep);
}

class ReviewQueueRowsCompanion extends UpdateCompanion<ReviewQueueRow> {
  final Value<String> id;
  final Value<String> termId;
  final Value<String> exerciseMode;
  final Value<String> response;
  final Value<int> clientSeq;
  final Value<String> answeredAt;
  final Value<bool> usedHint;
  final Value<bool> isPractice;
  final Value<int?> latencyMs;
  final Value<String?> sessionId;
  final Value<int?> ladderStep;
  final Value<int> rowid;
  const ReviewQueueRowsCompanion({
    this.id = const Value.absent(),
    this.termId = const Value.absent(),
    this.exerciseMode = const Value.absent(),
    this.response = const Value.absent(),
    this.clientSeq = const Value.absent(),
    this.answeredAt = const Value.absent(),
    this.usedHint = const Value.absent(),
    this.isPractice = const Value.absent(),
    this.latencyMs = const Value.absent(),
    this.sessionId = const Value.absent(),
    this.ladderStep = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  ReviewQueueRowsCompanion.insert({
    required String id,
    required String termId,
    required String exerciseMode,
    required String response,
    required int clientSeq,
    required String answeredAt,
    this.usedHint = const Value.absent(),
    this.isPractice = const Value.absent(),
    this.latencyMs = const Value.absent(),
    this.sessionId = const Value.absent(),
    this.ladderStep = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : id = Value(id),
       termId = Value(termId),
       exerciseMode = Value(exerciseMode),
       response = Value(response),
       clientSeq = Value(clientSeq),
       answeredAt = Value(answeredAt);
  static Insertable<ReviewQueueRow> custom({
    Expression<String>? id,
    Expression<String>? termId,
    Expression<String>? exerciseMode,
    Expression<String>? response,
    Expression<int>? clientSeq,
    Expression<String>? answeredAt,
    Expression<bool>? usedHint,
    Expression<bool>? isPractice,
    Expression<int>? latencyMs,
    Expression<String>? sessionId,
    Expression<int>? ladderStep,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (termId != null) 'term_id': termId,
      if (exerciseMode != null) 'exercise_mode': exerciseMode,
      if (response != null) 'response': response,
      if (clientSeq != null) 'client_seq': clientSeq,
      if (answeredAt != null) 'answered_at': answeredAt,
      if (usedHint != null) 'used_hint': usedHint,
      if (isPractice != null) 'is_practice': isPractice,
      if (latencyMs != null) 'latency_ms': latencyMs,
      if (sessionId != null) 'session_id': sessionId,
      if (ladderStep != null) 'ladder_step': ladderStep,
      if (rowid != null) 'rowid': rowid,
    });
  }

  ReviewQueueRowsCompanion copyWith({
    Value<String>? id,
    Value<String>? termId,
    Value<String>? exerciseMode,
    Value<String>? response,
    Value<int>? clientSeq,
    Value<String>? answeredAt,
    Value<bool>? usedHint,
    Value<bool>? isPractice,
    Value<int?>? latencyMs,
    Value<String?>? sessionId,
    Value<int?>? ladderStep,
    Value<int>? rowid,
  }) {
    return ReviewQueueRowsCompanion(
      id: id ?? this.id,
      termId: termId ?? this.termId,
      exerciseMode: exerciseMode ?? this.exerciseMode,
      response: response ?? this.response,
      clientSeq: clientSeq ?? this.clientSeq,
      answeredAt: answeredAt ?? this.answeredAt,
      usedHint: usedHint ?? this.usedHint,
      isPractice: isPractice ?? this.isPractice,
      latencyMs: latencyMs ?? this.latencyMs,
      sessionId: sessionId ?? this.sessionId,
      ladderStep: ladderStep ?? this.ladderStep,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (termId.present) {
      map['term_id'] = Variable<String>(termId.value);
    }
    if (exerciseMode.present) {
      map['exercise_mode'] = Variable<String>(exerciseMode.value);
    }
    if (response.present) {
      map['response'] = Variable<String>(response.value);
    }
    if (clientSeq.present) {
      map['client_seq'] = Variable<int>(clientSeq.value);
    }
    if (answeredAt.present) {
      map['answered_at'] = Variable<String>(answeredAt.value);
    }
    if (usedHint.present) {
      map['used_hint'] = Variable<bool>(usedHint.value);
    }
    if (isPractice.present) {
      map['is_practice'] = Variable<bool>(isPractice.value);
    }
    if (latencyMs.present) {
      map['latency_ms'] = Variable<int>(latencyMs.value);
    }
    if (sessionId.present) {
      map['session_id'] = Variable<String>(sessionId.value);
    }
    if (ladderStep.present) {
      map['ladder_step'] = Variable<int>(ladderStep.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ReviewQueueRowsCompanion(')
          ..write('id: $id, ')
          ..write('termId: $termId, ')
          ..write('exerciseMode: $exerciseMode, ')
          ..write('response: $response, ')
          ..write('clientSeq: $clientSeq, ')
          ..write('answeredAt: $answeredAt, ')
          ..write('usedHint: $usedHint, ')
          ..write('isPractice: $isPractice, ')
          ..write('latencyMs: $latencyMs, ')
          ..write('sessionId: $sessionId, ')
          ..write('ladderStep: $ladderStep, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $ExposureQueueRowsTable extends ExposureQueueRows
    with TableInfo<$ExposureQueueRowsTable, ExposureQueueRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ExposureQueueRowsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _termIdMeta = const VerificationMeta('termId');
  @override
  late final GeneratedColumn<String> termId = GeneratedColumn<String>(
    'term_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _shownAtMeta = const VerificationMeta(
    'shownAt',
  );
  @override
  late final GeneratedColumn<String> shownAt = GeneratedColumn<String>(
    'shown_at',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _sessionIdMeta = const VerificationMeta(
    'sessionId',
  );
  @override
  late final GeneratedColumn<String> sessionId = GeneratedColumn<String>(
    'session_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [termId, shownAt, sessionId];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'exposure_queue_rows';
  @override
  VerificationContext validateIntegrity(
    Insertable<ExposureQueueRow> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('term_id')) {
      context.handle(
        _termIdMeta,
        termId.isAcceptableOrUnknown(data['term_id']!, _termIdMeta),
      );
    } else if (isInserting) {
      context.missing(_termIdMeta);
    }
    if (data.containsKey('shown_at')) {
      context.handle(
        _shownAtMeta,
        shownAt.isAcceptableOrUnknown(data['shown_at']!, _shownAtMeta),
      );
    } else if (isInserting) {
      context.missing(_shownAtMeta);
    }
    if (data.containsKey('session_id')) {
      context.handle(
        _sessionIdMeta,
        sessionId.isAcceptableOrUnknown(data['session_id']!, _sessionIdMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {termId};
  @override
  ExposureQueueRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return ExposureQueueRow(
      termId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}term_id'],
      )!,
      shownAt: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}shown_at'],
      )!,
      sessionId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}session_id'],
      ),
    );
  }

  @override
  $ExposureQueueRowsTable createAlias(String alias) {
    return $ExposureQueueRowsTable(attachedDatabase, alias);
  }
}

class ExposureQueueRow extends DataClass
    implements Insertable<ExposureQueueRow> {
  final String termId;
  final String shownAt;
  final String? sessionId;
  const ExposureQueueRow({
    required this.termId,
    required this.shownAt,
    this.sessionId,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['term_id'] = Variable<String>(termId);
    map['shown_at'] = Variable<String>(shownAt);
    if (!nullToAbsent || sessionId != null) {
      map['session_id'] = Variable<String>(sessionId);
    }
    return map;
  }

  ExposureQueueRowsCompanion toCompanion(bool nullToAbsent) {
    return ExposureQueueRowsCompanion(
      termId: Value(termId),
      shownAt: Value(shownAt),
      sessionId: sessionId == null && nullToAbsent
          ? const Value.absent()
          : Value(sessionId),
    );
  }

  factory ExposureQueueRow.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return ExposureQueueRow(
      termId: serializer.fromJson<String>(json['termId']),
      shownAt: serializer.fromJson<String>(json['shownAt']),
      sessionId: serializer.fromJson<String?>(json['sessionId']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'termId': serializer.toJson<String>(termId),
      'shownAt': serializer.toJson<String>(shownAt),
      'sessionId': serializer.toJson<String?>(sessionId),
    };
  }

  ExposureQueueRow copyWith({
    String? termId,
    String? shownAt,
    Value<String?> sessionId = const Value.absent(),
  }) => ExposureQueueRow(
    termId: termId ?? this.termId,
    shownAt: shownAt ?? this.shownAt,
    sessionId: sessionId.present ? sessionId.value : this.sessionId,
  );
  ExposureQueueRow copyWithCompanion(ExposureQueueRowsCompanion data) {
    return ExposureQueueRow(
      termId: data.termId.present ? data.termId.value : this.termId,
      shownAt: data.shownAt.present ? data.shownAt.value : this.shownAt,
      sessionId: data.sessionId.present ? data.sessionId.value : this.sessionId,
    );
  }

  @override
  String toString() {
    return (StringBuffer('ExposureQueueRow(')
          ..write('termId: $termId, ')
          ..write('shownAt: $shownAt, ')
          ..write('sessionId: $sessionId')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(termId, shownAt, sessionId);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ExposureQueueRow &&
          other.termId == this.termId &&
          other.shownAt == this.shownAt &&
          other.sessionId == this.sessionId);
}

class ExposureQueueRowsCompanion extends UpdateCompanion<ExposureQueueRow> {
  final Value<String> termId;
  final Value<String> shownAt;
  final Value<String?> sessionId;
  final Value<int> rowid;
  const ExposureQueueRowsCompanion({
    this.termId = const Value.absent(),
    this.shownAt = const Value.absent(),
    this.sessionId = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  ExposureQueueRowsCompanion.insert({
    required String termId,
    required String shownAt,
    this.sessionId = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : termId = Value(termId),
       shownAt = Value(shownAt);
  static Insertable<ExposureQueueRow> custom({
    Expression<String>? termId,
    Expression<String>? shownAt,
    Expression<String>? sessionId,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (termId != null) 'term_id': termId,
      if (shownAt != null) 'shown_at': shownAt,
      if (sessionId != null) 'session_id': sessionId,
      if (rowid != null) 'rowid': rowid,
    });
  }

  ExposureQueueRowsCompanion copyWith({
    Value<String>? termId,
    Value<String>? shownAt,
    Value<String?>? sessionId,
    Value<int>? rowid,
  }) {
    return ExposureQueueRowsCompanion(
      termId: termId ?? this.termId,
      shownAt: shownAt ?? this.shownAt,
      sessionId: sessionId ?? this.sessionId,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (termId.present) {
      map['term_id'] = Variable<String>(termId.value);
    }
    if (shownAt.present) {
      map['shown_at'] = Variable<String>(shownAt.value);
    }
    if (sessionId.present) {
      map['session_id'] = Variable<String>(sessionId.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ExposureQueueRowsCompanion(')
          ..write('termId: $termId, ')
          ..write('shownAt: $shownAt, ')
          ..write('sessionId: $sessionId, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $CachedImagesTable extends CachedImages
    with TableInfo<$CachedImagesTable, CachedImage> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedImagesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _urlMeta = const VerificationMeta('url');
  @override
  late final GeneratedColumn<String> url = GeneratedColumn<String>(
    'url',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _fileMeta = const VerificationMeta('file');
  @override
  late final GeneratedColumn<String> file = GeneratedColumn<String>(
    'file',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _bytesMeta = const VerificationMeta('bytes');
  @override
  late final GeneratedColumn<int> bytes = GeneratedColumn<int>(
    'bytes',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _usedAtMeta = const VerificationMeta('usedAt');
  @override
  late final GeneratedColumn<DateTime> usedAt = GeneratedColumn<DateTime>(
    'used_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [url, file, bytes, usedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_images';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedImage> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('url')) {
      context.handle(
        _urlMeta,
        url.isAcceptableOrUnknown(data['url']!, _urlMeta),
      );
    } else if (isInserting) {
      context.missing(_urlMeta);
    }
    if (data.containsKey('file')) {
      context.handle(
        _fileMeta,
        file.isAcceptableOrUnknown(data['file']!, _fileMeta),
      );
    } else if (isInserting) {
      context.missing(_fileMeta);
    }
    if (data.containsKey('bytes')) {
      context.handle(
        _bytesMeta,
        bytes.isAcceptableOrUnknown(data['bytes']!, _bytesMeta),
      );
    } else if (isInserting) {
      context.missing(_bytesMeta);
    }
    if (data.containsKey('used_at')) {
      context.handle(
        _usedAtMeta,
        usedAt.isAcceptableOrUnknown(data['used_at']!, _usedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_usedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {url};
  @override
  CachedImage map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedImage(
      url: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}url'],
      )!,
      file: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}file'],
      )!,
      bytes: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}bytes'],
      )!,
      usedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}used_at'],
      )!,
    );
  }

  @override
  $CachedImagesTable createAlias(String alias) {
    return $CachedImagesTable(attachedDatabase, alias);
  }
}

class CachedImage extends DataClass implements Insertable<CachedImage> {
  final String url;
  final String file;
  final int bytes;
  final DateTime usedAt;
  const CachedImage({
    required this.url,
    required this.file,
    required this.bytes,
    required this.usedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['url'] = Variable<String>(url);
    map['file'] = Variable<String>(file);
    map['bytes'] = Variable<int>(bytes);
    map['used_at'] = Variable<DateTime>(usedAt);
    return map;
  }

  CachedImagesCompanion toCompanion(bool nullToAbsent) {
    return CachedImagesCompanion(
      url: Value(url),
      file: Value(file),
      bytes: Value(bytes),
      usedAt: Value(usedAt),
    );
  }

  factory CachedImage.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedImage(
      url: serializer.fromJson<String>(json['url']),
      file: serializer.fromJson<String>(json['file']),
      bytes: serializer.fromJson<int>(json['bytes']),
      usedAt: serializer.fromJson<DateTime>(json['usedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'url': serializer.toJson<String>(url),
      'file': serializer.toJson<String>(file),
      'bytes': serializer.toJson<int>(bytes),
      'usedAt': serializer.toJson<DateTime>(usedAt),
    };
  }

  CachedImage copyWith({
    String? url,
    String? file,
    int? bytes,
    DateTime? usedAt,
  }) => CachedImage(
    url: url ?? this.url,
    file: file ?? this.file,
    bytes: bytes ?? this.bytes,
    usedAt: usedAt ?? this.usedAt,
  );
  CachedImage copyWithCompanion(CachedImagesCompanion data) {
    return CachedImage(
      url: data.url.present ? data.url.value : this.url,
      file: data.file.present ? data.file.value : this.file,
      bytes: data.bytes.present ? data.bytes.value : this.bytes,
      usedAt: data.usedAt.present ? data.usedAt.value : this.usedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedImage(')
          ..write('url: $url, ')
          ..write('file: $file, ')
          ..write('bytes: $bytes, ')
          ..write('usedAt: $usedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(url, file, bytes, usedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedImage &&
          other.url == this.url &&
          other.file == this.file &&
          other.bytes == this.bytes &&
          other.usedAt == this.usedAt);
}

class CachedImagesCompanion extends UpdateCompanion<CachedImage> {
  final Value<String> url;
  final Value<String> file;
  final Value<int> bytes;
  final Value<DateTime> usedAt;
  final Value<int> rowid;
  const CachedImagesCompanion({
    this.url = const Value.absent(),
    this.file = const Value.absent(),
    this.bytes = const Value.absent(),
    this.usedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedImagesCompanion.insert({
    required String url,
    required String file,
    required int bytes,
    required DateTime usedAt,
    this.rowid = const Value.absent(),
  }) : url = Value(url),
       file = Value(file),
       bytes = Value(bytes),
       usedAt = Value(usedAt);
  static Insertable<CachedImage> custom({
    Expression<String>? url,
    Expression<String>? file,
    Expression<int>? bytes,
    Expression<DateTime>? usedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (url != null) 'url': url,
      if (file != null) 'file': file,
      if (bytes != null) 'bytes': bytes,
      if (usedAt != null) 'used_at': usedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedImagesCompanion copyWith({
    Value<String>? url,
    Value<String>? file,
    Value<int>? bytes,
    Value<DateTime>? usedAt,
    Value<int>? rowid,
  }) {
    return CachedImagesCompanion(
      url: url ?? this.url,
      file: file ?? this.file,
      bytes: bytes ?? this.bytes,
      usedAt: usedAt ?? this.usedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (url.present) {
      map['url'] = Variable<String>(url.value);
    }
    if (file.present) {
      map['file'] = Variable<String>(file.value);
    }
    if (bytes.present) {
      map['bytes'] = Variable<int>(bytes.value);
    }
    if (usedAt.present) {
      map['used_at'] = Variable<DateTime>(usedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedImagesCompanion(')
          ..write('url: $url, ')
          ..write('file: $file, ')
          ..write('bytes: $bytes, ')
          ..write('usedAt: $usedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $CollectionsTable collections = $CollectionsTable(this);
  late final $CollectionItemsTable collectionItems = $CollectionItemsTable(
    this,
  );
  late final $TermsTable terms = $TermsTable(this);
  late final $TermProgressTable termProgress = $TermProgressTable(this);
  late final $SyncMetaTable syncMeta = $SyncMetaTable(this);
  late final $TriagedTermsTable triagedTerms = $TriagedTermsTable(this);
  late final $PendingGenerationsTable pendingGenerations =
      $PendingGenerationsTable(this);
  late final $DailyActivityTable dailyActivity = $DailyActivityTable(this);
  late final $ReviewQueueRowsTable reviewQueueRows = $ReviewQueueRowsTable(
    this,
  );
  late final $ExposureQueueRowsTable exposureQueueRows =
      $ExposureQueueRowsTable(this);
  late final $CachedImagesTable cachedImages = $CachedImagesTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    collections,
    collectionItems,
    terms,
    termProgress,
    syncMeta,
    triagedTerms,
    pendingGenerations,
    dailyActivity,
    reviewQueueRows,
    exposureQueueRows,
    cachedImages,
  ];
}

typedef $$CollectionsTableCreateCompanionBuilder =
    CollectionsCompanion Function({
      required String id,
      Value<String?> title,
      Value<String?> description,
      Value<String?> topic,
      Value<String?> sourceLang,
      Value<String?> targetLang,
      Value<int> itemsCount,
      Value<String?> source,
      Value<String?> type,
      Value<String?> imageUrl,
      Value<String?> imageAuthor,
      Value<String?> imageAuthorUrl,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$CollectionsTableUpdateCompanionBuilder =
    CollectionsCompanion Function({
      Value<String> id,
      Value<String?> title,
      Value<String?> description,
      Value<String?> topic,
      Value<String?> sourceLang,
      Value<String?> targetLang,
      Value<int> itemsCount,
      Value<String?> source,
      Value<String?> type,
      Value<String?> imageUrl,
      Value<String?> imageAuthor,
      Value<String?> imageAuthorUrl,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$CollectionsTableFilterComposer
    extends Composer<_$AppDatabase, $CollectionsTable> {
  $$CollectionsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get title => $composableBuilder(
    column: $table.title,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get description => $composableBuilder(
    column: $table.description,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get topic => $composableBuilder(
    column: $table.topic,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sourceLang => $composableBuilder(
    column: $table.sourceLang,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get targetLang => $composableBuilder(
    column: $table.targetLang,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get itemsCount => $composableBuilder(
    column: $table.itemsCount,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get source => $composableBuilder(
    column: $table.source,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imageUrl => $composableBuilder(
    column: $table.imageUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imageAuthor => $composableBuilder(
    column: $table.imageAuthor,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imageAuthorUrl => $composableBuilder(
    column: $table.imageAuthorUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CollectionsTableOrderingComposer
    extends Composer<_$AppDatabase, $CollectionsTable> {
  $$CollectionsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get title => $composableBuilder(
    column: $table.title,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get description => $composableBuilder(
    column: $table.description,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get topic => $composableBuilder(
    column: $table.topic,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sourceLang => $composableBuilder(
    column: $table.sourceLang,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get targetLang => $composableBuilder(
    column: $table.targetLang,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get itemsCount => $composableBuilder(
    column: $table.itemsCount,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get source => $composableBuilder(
    column: $table.source,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imageUrl => $composableBuilder(
    column: $table.imageUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imageAuthor => $composableBuilder(
    column: $table.imageAuthor,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imageAuthorUrl => $composableBuilder(
    column: $table.imageAuthorUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CollectionsTableAnnotationComposer
    extends Composer<_$AppDatabase, $CollectionsTable> {
  $$CollectionsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get title =>
      $composableBuilder(column: $table.title, builder: (column) => column);

  GeneratedColumn<String> get description => $composableBuilder(
    column: $table.description,
    builder: (column) => column,
  );

  GeneratedColumn<String> get topic =>
      $composableBuilder(column: $table.topic, builder: (column) => column);

  GeneratedColumn<String> get sourceLang => $composableBuilder(
    column: $table.sourceLang,
    builder: (column) => column,
  );

  GeneratedColumn<String> get targetLang => $composableBuilder(
    column: $table.targetLang,
    builder: (column) => column,
  );

  GeneratedColumn<int> get itemsCount => $composableBuilder(
    column: $table.itemsCount,
    builder: (column) => column,
  );

  GeneratedColumn<String> get source =>
      $composableBuilder(column: $table.source, builder: (column) => column);

  GeneratedColumn<String> get type =>
      $composableBuilder(column: $table.type, builder: (column) => column);

  GeneratedColumn<String> get imageUrl =>
      $composableBuilder(column: $table.imageUrl, builder: (column) => column);

  GeneratedColumn<String> get imageAuthor => $composableBuilder(
    column: $table.imageAuthor,
    builder: (column) => column,
  );

  GeneratedColumn<String> get imageAuthorUrl => $composableBuilder(
    column: $table.imageAuthorUrl,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$CollectionsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CollectionsTable,
          Collection,
          $$CollectionsTableFilterComposer,
          $$CollectionsTableOrderingComposer,
          $$CollectionsTableAnnotationComposer,
          $$CollectionsTableCreateCompanionBuilder,
          $$CollectionsTableUpdateCompanionBuilder,
          (
            Collection,
            BaseReferences<_$AppDatabase, $CollectionsTable, Collection>,
          ),
          Collection,
          PrefetchHooks Function()
        > {
  $$CollectionsTableTableManager(_$AppDatabase db, $CollectionsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CollectionsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CollectionsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CollectionsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> id = const Value.absent(),
                Value<String?> title = const Value.absent(),
                Value<String?> description = const Value.absent(),
                Value<String?> topic = const Value.absent(),
                Value<String?> sourceLang = const Value.absent(),
                Value<String?> targetLang = const Value.absent(),
                Value<int> itemsCount = const Value.absent(),
                Value<String?> source = const Value.absent(),
                Value<String?> type = const Value.absent(),
                Value<String?> imageUrl = const Value.absent(),
                Value<String?> imageAuthor = const Value.absent(),
                Value<String?> imageAuthorUrl = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CollectionsCompanion(
                id: id,
                title: title,
                description: description,
                topic: topic,
                sourceLang: sourceLang,
                targetLang: targetLang,
                itemsCount: itemsCount,
                source: source,
                type: type,
                imageUrl: imageUrl,
                imageAuthor: imageAuthor,
                imageAuthorUrl: imageAuthorUrl,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String id,
                Value<String?> title = const Value.absent(),
                Value<String?> description = const Value.absent(),
                Value<String?> topic = const Value.absent(),
                Value<String?> sourceLang = const Value.absent(),
                Value<String?> targetLang = const Value.absent(),
                Value<int> itemsCount = const Value.absent(),
                Value<String?> source = const Value.absent(),
                Value<String?> type = const Value.absent(),
                Value<String?> imageUrl = const Value.absent(),
                Value<String?> imageAuthor = const Value.absent(),
                Value<String?> imageAuthorUrl = const Value.absent(),
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => CollectionsCompanion.insert(
                id: id,
                title: title,
                description: description,
                topic: topic,
                sourceLang: sourceLang,
                targetLang: targetLang,
                itemsCount: itemsCount,
                source: source,
                type: type,
                imageUrl: imageUrl,
                imageAuthor: imageAuthor,
                imageAuthorUrl: imageAuthorUrl,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CollectionsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CollectionsTable,
      Collection,
      $$CollectionsTableFilterComposer,
      $$CollectionsTableOrderingComposer,
      $$CollectionsTableAnnotationComposer,
      $$CollectionsTableCreateCompanionBuilder,
      $$CollectionsTableUpdateCompanionBuilder,
      (
        Collection,
        BaseReferences<_$AppDatabase, $CollectionsTable, Collection>,
      ),
      Collection,
      PrefetchHooks Function()
    >;
typedef $$CollectionItemsTableCreateCompanionBuilder =
    CollectionItemsCompanion Function({
      required String collectionId,
      required String termId,
      Value<int> position,
      Value<String?> note,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$CollectionItemsTableUpdateCompanionBuilder =
    CollectionItemsCompanion Function({
      Value<String> collectionId,
      Value<String> termId,
      Value<int> position,
      Value<String?> note,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$CollectionItemsTableFilterComposer
    extends Composer<_$AppDatabase, $CollectionItemsTable> {
  $$CollectionItemsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get position => $composableBuilder(
    column: $table.position,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get note => $composableBuilder(
    column: $table.note,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CollectionItemsTableOrderingComposer
    extends Composer<_$AppDatabase, $CollectionItemsTable> {
  $$CollectionItemsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get position => $composableBuilder(
    column: $table.position,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get note => $composableBuilder(
    column: $table.note,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CollectionItemsTableAnnotationComposer
    extends Composer<_$AppDatabase, $CollectionItemsTable> {
  $$CollectionItemsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get termId =>
      $composableBuilder(column: $table.termId, builder: (column) => column);

  GeneratedColumn<int> get position =>
      $composableBuilder(column: $table.position, builder: (column) => column);

  GeneratedColumn<String> get note =>
      $composableBuilder(column: $table.note, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$CollectionItemsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CollectionItemsTable,
          CollectionItem,
          $$CollectionItemsTableFilterComposer,
          $$CollectionItemsTableOrderingComposer,
          $$CollectionItemsTableAnnotationComposer,
          $$CollectionItemsTableCreateCompanionBuilder,
          $$CollectionItemsTableUpdateCompanionBuilder,
          (
            CollectionItem,
            BaseReferences<
              _$AppDatabase,
              $CollectionItemsTable,
              CollectionItem
            >,
          ),
          CollectionItem,
          PrefetchHooks Function()
        > {
  $$CollectionItemsTableTableManager(
    _$AppDatabase db,
    $CollectionItemsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CollectionItemsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CollectionItemsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CollectionItemsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> collectionId = const Value.absent(),
                Value<String> termId = const Value.absent(),
                Value<int> position = const Value.absent(),
                Value<String?> note = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CollectionItemsCompanion(
                collectionId: collectionId,
                termId: termId,
                position: position,
                note: note,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String collectionId,
                required String termId,
                Value<int> position = const Value.absent(),
                Value<String?> note = const Value.absent(),
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => CollectionItemsCompanion.insert(
                collectionId: collectionId,
                termId: termId,
                position: position,
                note: note,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CollectionItemsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CollectionItemsTable,
      CollectionItem,
      $$CollectionItemsTableFilterComposer,
      $$CollectionItemsTableOrderingComposer,
      $$CollectionItemsTableAnnotationComposer,
      $$CollectionItemsTableCreateCompanionBuilder,
      $$CollectionItemsTableUpdateCompanionBuilder,
      (
        CollectionItem,
        BaseReferences<_$AppDatabase, $CollectionItemsTable, CollectionItem>,
      ),
      CollectionItem,
      PrefetchHooks Function()
    >;
typedef $$TermsTableCreateCompanionBuilder =
    TermsCompanion Function({
      required String id,
      Value<String?> termText,
      Value<String> type,
      Value<String?> transcription,
      Value<String?> translation,
      Value<String?> example,
      Value<String?> exampleTranslation,
      Value<String?> imageUrl,
      Value<String?> imageAuthor,
      Value<String?> imageAuthorUrl,
      Value<String?> acceptedVariants,
      Value<String?> exampleDistractors,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$TermsTableUpdateCompanionBuilder =
    TermsCompanion Function({
      Value<String> id,
      Value<String?> termText,
      Value<String> type,
      Value<String?> transcription,
      Value<String?> translation,
      Value<String?> example,
      Value<String?> exampleTranslation,
      Value<String?> imageUrl,
      Value<String?> imageAuthor,
      Value<String?> imageAuthorUrl,
      Value<String?> acceptedVariants,
      Value<String?> exampleDistractors,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$TermsTableFilterComposer extends Composer<_$AppDatabase, $TermsTable> {
  $$TermsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get termText => $composableBuilder(
    column: $table.termText,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get transcription => $composableBuilder(
    column: $table.transcription,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get translation => $composableBuilder(
    column: $table.translation,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get example => $composableBuilder(
    column: $table.example,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get exampleTranslation => $composableBuilder(
    column: $table.exampleTranslation,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imageUrl => $composableBuilder(
    column: $table.imageUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imageAuthor => $composableBuilder(
    column: $table.imageAuthor,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imageAuthorUrl => $composableBuilder(
    column: $table.imageAuthorUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get acceptedVariants => $composableBuilder(
    column: $table.acceptedVariants,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get exampleDistractors => $composableBuilder(
    column: $table.exampleDistractors,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$TermsTableOrderingComposer
    extends Composer<_$AppDatabase, $TermsTable> {
  $$TermsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get termText => $composableBuilder(
    column: $table.termText,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get type => $composableBuilder(
    column: $table.type,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get transcription => $composableBuilder(
    column: $table.transcription,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get translation => $composableBuilder(
    column: $table.translation,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get example => $composableBuilder(
    column: $table.example,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get exampleTranslation => $composableBuilder(
    column: $table.exampleTranslation,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imageUrl => $composableBuilder(
    column: $table.imageUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imageAuthor => $composableBuilder(
    column: $table.imageAuthor,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imageAuthorUrl => $composableBuilder(
    column: $table.imageAuthorUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get acceptedVariants => $composableBuilder(
    column: $table.acceptedVariants,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get exampleDistractors => $composableBuilder(
    column: $table.exampleDistractors,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$TermsTableAnnotationComposer
    extends Composer<_$AppDatabase, $TermsTable> {
  $$TermsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get termText =>
      $composableBuilder(column: $table.termText, builder: (column) => column);

  GeneratedColumn<String> get type =>
      $composableBuilder(column: $table.type, builder: (column) => column);

  GeneratedColumn<String> get transcription => $composableBuilder(
    column: $table.transcription,
    builder: (column) => column,
  );

  GeneratedColumn<String> get translation => $composableBuilder(
    column: $table.translation,
    builder: (column) => column,
  );

  GeneratedColumn<String> get example =>
      $composableBuilder(column: $table.example, builder: (column) => column);

  GeneratedColumn<String> get exampleTranslation => $composableBuilder(
    column: $table.exampleTranslation,
    builder: (column) => column,
  );

  GeneratedColumn<String> get imageUrl =>
      $composableBuilder(column: $table.imageUrl, builder: (column) => column);

  GeneratedColumn<String> get imageAuthor => $composableBuilder(
    column: $table.imageAuthor,
    builder: (column) => column,
  );

  GeneratedColumn<String> get imageAuthorUrl => $composableBuilder(
    column: $table.imageAuthorUrl,
    builder: (column) => column,
  );

  GeneratedColumn<String> get acceptedVariants => $composableBuilder(
    column: $table.acceptedVariants,
    builder: (column) => column,
  );

  GeneratedColumn<String> get exampleDistractors => $composableBuilder(
    column: $table.exampleDistractors,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$TermsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $TermsTable,
          Term,
          $$TermsTableFilterComposer,
          $$TermsTableOrderingComposer,
          $$TermsTableAnnotationComposer,
          $$TermsTableCreateCompanionBuilder,
          $$TermsTableUpdateCompanionBuilder,
          (Term, BaseReferences<_$AppDatabase, $TermsTable, Term>),
          Term,
          PrefetchHooks Function()
        > {
  $$TermsTableTableManager(_$AppDatabase db, $TermsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$TermsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$TermsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$TermsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> id = const Value.absent(),
                Value<String?> termText = const Value.absent(),
                Value<String> type = const Value.absent(),
                Value<String?> transcription = const Value.absent(),
                Value<String?> translation = const Value.absent(),
                Value<String?> example = const Value.absent(),
                Value<String?> exampleTranslation = const Value.absent(),
                Value<String?> imageUrl = const Value.absent(),
                Value<String?> imageAuthor = const Value.absent(),
                Value<String?> imageAuthorUrl = const Value.absent(),
                Value<String?> acceptedVariants = const Value.absent(),
                Value<String?> exampleDistractors = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => TermsCompanion(
                id: id,
                termText: termText,
                type: type,
                transcription: transcription,
                translation: translation,
                example: example,
                exampleTranslation: exampleTranslation,
                imageUrl: imageUrl,
                imageAuthor: imageAuthor,
                imageAuthorUrl: imageAuthorUrl,
                acceptedVariants: acceptedVariants,
                exampleDistractors: exampleDistractors,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String id,
                Value<String?> termText = const Value.absent(),
                Value<String> type = const Value.absent(),
                Value<String?> transcription = const Value.absent(),
                Value<String?> translation = const Value.absent(),
                Value<String?> example = const Value.absent(),
                Value<String?> exampleTranslation = const Value.absent(),
                Value<String?> imageUrl = const Value.absent(),
                Value<String?> imageAuthor = const Value.absent(),
                Value<String?> imageAuthorUrl = const Value.absent(),
                Value<String?> acceptedVariants = const Value.absent(),
                Value<String?> exampleDistractors = const Value.absent(),
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => TermsCompanion.insert(
                id: id,
                termText: termText,
                type: type,
                transcription: transcription,
                translation: translation,
                example: example,
                exampleTranslation: exampleTranslation,
                imageUrl: imageUrl,
                imageAuthor: imageAuthor,
                imageAuthorUrl: imageAuthorUrl,
                acceptedVariants: acceptedVariants,
                exampleDistractors: exampleDistractors,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$TermsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $TermsTable,
      Term,
      $$TermsTableFilterComposer,
      $$TermsTableOrderingComposer,
      $$TermsTableAnnotationComposer,
      $$TermsTableCreateCompanionBuilder,
      $$TermsTableUpdateCompanionBuilder,
      (Term, BaseReferences<_$AppDatabase, $TermsTable, Term>),
      Term,
      PrefetchHooks Function()
    >;
typedef $$TermProgressTableCreateCompanionBuilder =
    TermProgressCompanion Function({
      required String termId,
      Value<String> state,
      Value<double> easeFactor,
      Value<int> intervalDays,
      Value<DateTime?> dueAt,
      Value<int> reps,
      Value<int> lapses,
      Value<DateTime?> lastReviewedAt,
      Value<String> acquisition,
      Value<int> learningStep,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$TermProgressTableUpdateCompanionBuilder =
    TermProgressCompanion Function({
      Value<String> termId,
      Value<String> state,
      Value<double> easeFactor,
      Value<int> intervalDays,
      Value<DateTime?> dueAt,
      Value<int> reps,
      Value<int> lapses,
      Value<DateTime?> lastReviewedAt,
      Value<String> acquisition,
      Value<int> learningStep,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$TermProgressTableFilterComposer
    extends Composer<_$AppDatabase, $TermProgressTable> {
  $$TermProgressTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get state => $composableBuilder(
    column: $table.state,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<double> get easeFactor => $composableBuilder(
    column: $table.easeFactor,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get intervalDays => $composableBuilder(
    column: $table.intervalDays,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get dueAt => $composableBuilder(
    column: $table.dueAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get reps => $composableBuilder(
    column: $table.reps,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get lapses => $composableBuilder(
    column: $table.lapses,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get lastReviewedAt => $composableBuilder(
    column: $table.lastReviewedAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get acquisition => $composableBuilder(
    column: $table.acquisition,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get learningStep => $composableBuilder(
    column: $table.learningStep,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$TermProgressTableOrderingComposer
    extends Composer<_$AppDatabase, $TermProgressTable> {
  $$TermProgressTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get state => $composableBuilder(
    column: $table.state,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<double> get easeFactor => $composableBuilder(
    column: $table.easeFactor,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get intervalDays => $composableBuilder(
    column: $table.intervalDays,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get dueAt => $composableBuilder(
    column: $table.dueAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get reps => $composableBuilder(
    column: $table.reps,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get lapses => $composableBuilder(
    column: $table.lapses,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get lastReviewedAt => $composableBuilder(
    column: $table.lastReviewedAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get acquisition => $composableBuilder(
    column: $table.acquisition,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get learningStep => $composableBuilder(
    column: $table.learningStep,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$TermProgressTableAnnotationComposer
    extends Composer<_$AppDatabase, $TermProgressTable> {
  $$TermProgressTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get termId =>
      $composableBuilder(column: $table.termId, builder: (column) => column);

  GeneratedColumn<String> get state =>
      $composableBuilder(column: $table.state, builder: (column) => column);

  GeneratedColumn<double> get easeFactor => $composableBuilder(
    column: $table.easeFactor,
    builder: (column) => column,
  );

  GeneratedColumn<int> get intervalDays => $composableBuilder(
    column: $table.intervalDays,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get dueAt =>
      $composableBuilder(column: $table.dueAt, builder: (column) => column);

  GeneratedColumn<int> get reps =>
      $composableBuilder(column: $table.reps, builder: (column) => column);

  GeneratedColumn<int> get lapses =>
      $composableBuilder(column: $table.lapses, builder: (column) => column);

  GeneratedColumn<DateTime> get lastReviewedAt => $composableBuilder(
    column: $table.lastReviewedAt,
    builder: (column) => column,
  );

  GeneratedColumn<String> get acquisition => $composableBuilder(
    column: $table.acquisition,
    builder: (column) => column,
  );

  GeneratedColumn<int> get learningStep => $composableBuilder(
    column: $table.learningStep,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$TermProgressTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $TermProgressTable,
          TermProgressData,
          $$TermProgressTableFilterComposer,
          $$TermProgressTableOrderingComposer,
          $$TermProgressTableAnnotationComposer,
          $$TermProgressTableCreateCompanionBuilder,
          $$TermProgressTableUpdateCompanionBuilder,
          (
            TermProgressData,
            BaseReferences<_$AppDatabase, $TermProgressTable, TermProgressData>,
          ),
          TermProgressData,
          PrefetchHooks Function()
        > {
  $$TermProgressTableTableManager(_$AppDatabase db, $TermProgressTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$TermProgressTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$TermProgressTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$TermProgressTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> termId = const Value.absent(),
                Value<String> state = const Value.absent(),
                Value<double> easeFactor = const Value.absent(),
                Value<int> intervalDays = const Value.absent(),
                Value<DateTime?> dueAt = const Value.absent(),
                Value<int> reps = const Value.absent(),
                Value<int> lapses = const Value.absent(),
                Value<DateTime?> lastReviewedAt = const Value.absent(),
                Value<String> acquisition = const Value.absent(),
                Value<int> learningStep = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => TermProgressCompanion(
                termId: termId,
                state: state,
                easeFactor: easeFactor,
                intervalDays: intervalDays,
                dueAt: dueAt,
                reps: reps,
                lapses: lapses,
                lastReviewedAt: lastReviewedAt,
                acquisition: acquisition,
                learningStep: learningStep,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String termId,
                Value<String> state = const Value.absent(),
                Value<double> easeFactor = const Value.absent(),
                Value<int> intervalDays = const Value.absent(),
                Value<DateTime?> dueAt = const Value.absent(),
                Value<int> reps = const Value.absent(),
                Value<int> lapses = const Value.absent(),
                Value<DateTime?> lastReviewedAt = const Value.absent(),
                Value<String> acquisition = const Value.absent(),
                Value<int> learningStep = const Value.absent(),
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => TermProgressCompanion.insert(
                termId: termId,
                state: state,
                easeFactor: easeFactor,
                intervalDays: intervalDays,
                dueAt: dueAt,
                reps: reps,
                lapses: lapses,
                lastReviewedAt: lastReviewedAt,
                acquisition: acquisition,
                learningStep: learningStep,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$TermProgressTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $TermProgressTable,
      TermProgressData,
      $$TermProgressTableFilterComposer,
      $$TermProgressTableOrderingComposer,
      $$TermProgressTableAnnotationComposer,
      $$TermProgressTableCreateCompanionBuilder,
      $$TermProgressTableUpdateCompanionBuilder,
      (
        TermProgressData,
        BaseReferences<_$AppDatabase, $TermProgressTable, TermProgressData>,
      ),
      TermProgressData,
      PrefetchHooks Function()
    >;
typedef $$SyncMetaTableCreateCompanionBuilder =
    SyncMetaCompanion Function({
      required String key,
      Value<String?> value,
      Value<int> rowid,
    });
typedef $$SyncMetaTableUpdateCompanionBuilder =
    SyncMetaCompanion Function({
      Value<String> key,
      Value<String?> value,
      Value<int> rowid,
    });

class $$SyncMetaTableFilterComposer
    extends Composer<_$AppDatabase, $SyncMetaTable> {
  $$SyncMetaTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get key => $composableBuilder(
    column: $table.key,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get value => $composableBuilder(
    column: $table.value,
    builder: (column) => ColumnFilters(column),
  );
}

class $$SyncMetaTableOrderingComposer
    extends Composer<_$AppDatabase, $SyncMetaTable> {
  $$SyncMetaTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get key => $composableBuilder(
    column: $table.key,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get value => $composableBuilder(
    column: $table.value,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$SyncMetaTableAnnotationComposer
    extends Composer<_$AppDatabase, $SyncMetaTable> {
  $$SyncMetaTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get key =>
      $composableBuilder(column: $table.key, builder: (column) => column);

  GeneratedColumn<String> get value =>
      $composableBuilder(column: $table.value, builder: (column) => column);
}

class $$SyncMetaTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $SyncMetaTable,
          SyncMetaData,
          $$SyncMetaTableFilterComposer,
          $$SyncMetaTableOrderingComposer,
          $$SyncMetaTableAnnotationComposer,
          $$SyncMetaTableCreateCompanionBuilder,
          $$SyncMetaTableUpdateCompanionBuilder,
          (
            SyncMetaData,
            BaseReferences<_$AppDatabase, $SyncMetaTable, SyncMetaData>,
          ),
          SyncMetaData,
          PrefetchHooks Function()
        > {
  $$SyncMetaTableTableManager(_$AppDatabase db, $SyncMetaTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SyncMetaTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SyncMetaTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SyncMetaTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> key = const Value.absent(),
                Value<String?> value = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => SyncMetaCompanion(key: key, value: value, rowid: rowid),
          createCompanionCallback:
              ({
                required String key,
                Value<String?> value = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => SyncMetaCompanion.insert(
                key: key,
                value: value,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$SyncMetaTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $SyncMetaTable,
      SyncMetaData,
      $$SyncMetaTableFilterComposer,
      $$SyncMetaTableOrderingComposer,
      $$SyncMetaTableAnnotationComposer,
      $$SyncMetaTableCreateCompanionBuilder,
      $$SyncMetaTableUpdateCompanionBuilder,
      (
        SyncMetaData,
        BaseReferences<_$AppDatabase, $SyncMetaTable, SyncMetaData>,
      ),
      SyncMetaData,
      PrefetchHooks Function()
    >;
typedef $$TriagedTermsTableCreateCompanionBuilder =
    TriagedTermsCompanion Function({
      required String termId,
      Value<String?> collectionId,
      required DateTime decidedAt,
      Value<int> rowid,
    });
typedef $$TriagedTermsTableUpdateCompanionBuilder =
    TriagedTermsCompanion Function({
      Value<String> termId,
      Value<String?> collectionId,
      Value<DateTime> decidedAt,
      Value<int> rowid,
    });

class $$TriagedTermsTableFilterComposer
    extends Composer<_$AppDatabase, $TriagedTermsTable> {
  $$TriagedTermsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get decidedAt => $composableBuilder(
    column: $table.decidedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$TriagedTermsTableOrderingComposer
    extends Composer<_$AppDatabase, $TriagedTermsTable> {
  $$TriagedTermsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get decidedAt => $composableBuilder(
    column: $table.decidedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$TriagedTermsTableAnnotationComposer
    extends Composer<_$AppDatabase, $TriagedTermsTable> {
  $$TriagedTermsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get termId =>
      $composableBuilder(column: $table.termId, builder: (column) => column);

  GeneratedColumn<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get decidedAt =>
      $composableBuilder(column: $table.decidedAt, builder: (column) => column);
}

class $$TriagedTermsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $TriagedTermsTable,
          TriagedTerm,
          $$TriagedTermsTableFilterComposer,
          $$TriagedTermsTableOrderingComposer,
          $$TriagedTermsTableAnnotationComposer,
          $$TriagedTermsTableCreateCompanionBuilder,
          $$TriagedTermsTableUpdateCompanionBuilder,
          (
            TriagedTerm,
            BaseReferences<_$AppDatabase, $TriagedTermsTable, TriagedTerm>,
          ),
          TriagedTerm,
          PrefetchHooks Function()
        > {
  $$TriagedTermsTableTableManager(_$AppDatabase db, $TriagedTermsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$TriagedTermsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$TriagedTermsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$TriagedTermsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> termId = const Value.absent(),
                Value<String?> collectionId = const Value.absent(),
                Value<DateTime> decidedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => TriagedTermsCompanion(
                termId: termId,
                collectionId: collectionId,
                decidedAt: decidedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String termId,
                Value<String?> collectionId = const Value.absent(),
                required DateTime decidedAt,
                Value<int> rowid = const Value.absent(),
              }) => TriagedTermsCompanion.insert(
                termId: termId,
                collectionId: collectionId,
                decidedAt: decidedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$TriagedTermsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $TriagedTermsTable,
      TriagedTerm,
      $$TriagedTermsTableFilterComposer,
      $$TriagedTermsTableOrderingComposer,
      $$TriagedTermsTableAnnotationComposer,
      $$TriagedTermsTableCreateCompanionBuilder,
      $$TriagedTermsTableUpdateCompanionBuilder,
      (
        TriagedTerm,
        BaseReferences<_$AppDatabase, $TriagedTermsTable, TriagedTerm>,
      ),
      TriagedTerm,
      PrefetchHooks Function()
    >;
typedef $$PendingGenerationsTableCreateCompanionBuilder =
    PendingGenerationsCompanion Function({
      required String id,
      required String topic,
      Value<String> status,
      Value<String?> collectionId,
      Value<String?> error,
      Value<int?> requested,
      Value<int?> delivered,
      Value<String> sourceLang,
      Value<String> targetLang,
      Value<String> levelsCsv,
      Value<int> size,
      Value<bool> sent,
      Value<bool> targetLangExplicit,
      required DateTime createdAt,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$PendingGenerationsTableUpdateCompanionBuilder =
    PendingGenerationsCompanion Function({
      Value<String> id,
      Value<String> topic,
      Value<String> status,
      Value<String?> collectionId,
      Value<String?> error,
      Value<int?> requested,
      Value<int?> delivered,
      Value<String> sourceLang,
      Value<String> targetLang,
      Value<String> levelsCsv,
      Value<int> size,
      Value<bool> sent,
      Value<bool> targetLangExplicit,
      Value<DateTime> createdAt,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$PendingGenerationsTableFilterComposer
    extends Composer<_$AppDatabase, $PendingGenerationsTable> {
  $$PendingGenerationsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get topic => $composableBuilder(
    column: $table.topic,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get error => $composableBuilder(
    column: $table.error,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get requested => $composableBuilder(
    column: $table.requested,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get delivered => $composableBuilder(
    column: $table.delivered,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sourceLang => $composableBuilder(
    column: $table.sourceLang,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get targetLang => $composableBuilder(
    column: $table.targetLang,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get levelsCsv => $composableBuilder(
    column: $table.levelsCsv,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get size => $composableBuilder(
    column: $table.size,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get sent => $composableBuilder(
    column: $table.sent,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get targetLangExplicit => $composableBuilder(
    column: $table.targetLangExplicit,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$PendingGenerationsTableOrderingComposer
    extends Composer<_$AppDatabase, $PendingGenerationsTable> {
  $$PendingGenerationsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get topic => $composableBuilder(
    column: $table.topic,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get error => $composableBuilder(
    column: $table.error,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get requested => $composableBuilder(
    column: $table.requested,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get delivered => $composableBuilder(
    column: $table.delivered,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sourceLang => $composableBuilder(
    column: $table.sourceLang,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get targetLang => $composableBuilder(
    column: $table.targetLang,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get levelsCsv => $composableBuilder(
    column: $table.levelsCsv,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get size => $composableBuilder(
    column: $table.size,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get sent => $composableBuilder(
    column: $table.sent,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get targetLangExplicit => $composableBuilder(
    column: $table.targetLangExplicit,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PendingGenerationsTableAnnotationComposer
    extends Composer<_$AppDatabase, $PendingGenerationsTable> {
  $$PendingGenerationsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get topic =>
      $composableBuilder(column: $table.topic, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get collectionId => $composableBuilder(
    column: $table.collectionId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get error =>
      $composableBuilder(column: $table.error, builder: (column) => column);

  GeneratedColumn<int> get requested =>
      $composableBuilder(column: $table.requested, builder: (column) => column);

  GeneratedColumn<int> get delivered =>
      $composableBuilder(column: $table.delivered, builder: (column) => column);

  GeneratedColumn<String> get sourceLang => $composableBuilder(
    column: $table.sourceLang,
    builder: (column) => column,
  );

  GeneratedColumn<String> get targetLang => $composableBuilder(
    column: $table.targetLang,
    builder: (column) => column,
  );

  GeneratedColumn<String> get levelsCsv =>
      $composableBuilder(column: $table.levelsCsv, builder: (column) => column);

  GeneratedColumn<int> get size =>
      $composableBuilder(column: $table.size, builder: (column) => column);

  GeneratedColumn<bool> get sent =>
      $composableBuilder(column: $table.sent, builder: (column) => column);

  GeneratedColumn<bool> get targetLangExplicit => $composableBuilder(
    column: $table.targetLangExplicit,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$PendingGenerationsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $PendingGenerationsTable,
          PendingGeneration,
          $$PendingGenerationsTableFilterComposer,
          $$PendingGenerationsTableOrderingComposer,
          $$PendingGenerationsTableAnnotationComposer,
          $$PendingGenerationsTableCreateCompanionBuilder,
          $$PendingGenerationsTableUpdateCompanionBuilder,
          (
            PendingGeneration,
            BaseReferences<
              _$AppDatabase,
              $PendingGenerationsTable,
              PendingGeneration
            >,
          ),
          PendingGeneration,
          PrefetchHooks Function()
        > {
  $$PendingGenerationsTableTableManager(
    _$AppDatabase db,
    $PendingGenerationsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PendingGenerationsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PendingGenerationsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PendingGenerationsTableAnnotationComposer(
                $db: db,
                $table: table,
              ),
          updateCompanionCallback:
              ({
                Value<String> id = const Value.absent(),
                Value<String> topic = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<String?> collectionId = const Value.absent(),
                Value<String?> error = const Value.absent(),
                Value<int?> requested = const Value.absent(),
                Value<int?> delivered = const Value.absent(),
                Value<String> sourceLang = const Value.absent(),
                Value<String> targetLang = const Value.absent(),
                Value<String> levelsCsv = const Value.absent(),
                Value<int> size = const Value.absent(),
                Value<bool> sent = const Value.absent(),
                Value<bool> targetLangExplicit = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PendingGenerationsCompanion(
                id: id,
                topic: topic,
                status: status,
                collectionId: collectionId,
                error: error,
                requested: requested,
                delivered: delivered,
                sourceLang: sourceLang,
                targetLang: targetLang,
                levelsCsv: levelsCsv,
                size: size,
                sent: sent,
                targetLangExplicit: targetLangExplicit,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String id,
                required String topic,
                Value<String> status = const Value.absent(),
                Value<String?> collectionId = const Value.absent(),
                Value<String?> error = const Value.absent(),
                Value<int?> requested = const Value.absent(),
                Value<int?> delivered = const Value.absent(),
                Value<String> sourceLang = const Value.absent(),
                Value<String> targetLang = const Value.absent(),
                Value<String> levelsCsv = const Value.absent(),
                Value<int> size = const Value.absent(),
                Value<bool> sent = const Value.absent(),
                Value<bool> targetLangExplicit = const Value.absent(),
                required DateTime createdAt,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => PendingGenerationsCompanion.insert(
                id: id,
                topic: topic,
                status: status,
                collectionId: collectionId,
                error: error,
                requested: requested,
                delivered: delivered,
                sourceLang: sourceLang,
                targetLang: targetLang,
                levelsCsv: levelsCsv,
                size: size,
                sent: sent,
                targetLangExplicit: targetLangExplicit,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PendingGenerationsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $PendingGenerationsTable,
      PendingGeneration,
      $$PendingGenerationsTableFilterComposer,
      $$PendingGenerationsTableOrderingComposer,
      $$PendingGenerationsTableAnnotationComposer,
      $$PendingGenerationsTableCreateCompanionBuilder,
      $$PendingGenerationsTableUpdateCompanionBuilder,
      (
        PendingGeneration,
        BaseReferences<
          _$AppDatabase,
          $PendingGenerationsTable,
          PendingGeneration
        >,
      ),
      PendingGeneration,
      PrefetchHooks Function()
    >;
typedef $$DailyActivityTableCreateCompanionBuilder =
    DailyActivityCompanion Function({
      required String day,
      Value<int> reviews,
      Value<int> rowid,
    });
typedef $$DailyActivityTableUpdateCompanionBuilder =
    DailyActivityCompanion Function({
      Value<String> day,
      Value<int> reviews,
      Value<int> rowid,
    });

class $$DailyActivityTableFilterComposer
    extends Composer<_$AppDatabase, $DailyActivityTable> {
  $$DailyActivityTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get day => $composableBuilder(
    column: $table.day,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get reviews => $composableBuilder(
    column: $table.reviews,
    builder: (column) => ColumnFilters(column),
  );
}

class $$DailyActivityTableOrderingComposer
    extends Composer<_$AppDatabase, $DailyActivityTable> {
  $$DailyActivityTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get day => $composableBuilder(
    column: $table.day,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get reviews => $composableBuilder(
    column: $table.reviews,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$DailyActivityTableAnnotationComposer
    extends Composer<_$AppDatabase, $DailyActivityTable> {
  $$DailyActivityTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get day =>
      $composableBuilder(column: $table.day, builder: (column) => column);

  GeneratedColumn<int> get reviews =>
      $composableBuilder(column: $table.reviews, builder: (column) => column);
}

class $$DailyActivityTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $DailyActivityTable,
          DailyActivityData,
          $$DailyActivityTableFilterComposer,
          $$DailyActivityTableOrderingComposer,
          $$DailyActivityTableAnnotationComposer,
          $$DailyActivityTableCreateCompanionBuilder,
          $$DailyActivityTableUpdateCompanionBuilder,
          (
            DailyActivityData,
            BaseReferences<
              _$AppDatabase,
              $DailyActivityTable,
              DailyActivityData
            >,
          ),
          DailyActivityData,
          PrefetchHooks Function()
        > {
  $$DailyActivityTableTableManager(_$AppDatabase db, $DailyActivityTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$DailyActivityTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$DailyActivityTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$DailyActivityTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> day = const Value.absent(),
                Value<int> reviews = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => DailyActivityCompanion(
                day: day,
                reviews: reviews,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String day,
                Value<int> reviews = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => DailyActivityCompanion.insert(
                day: day,
                reviews: reviews,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$DailyActivityTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $DailyActivityTable,
      DailyActivityData,
      $$DailyActivityTableFilterComposer,
      $$DailyActivityTableOrderingComposer,
      $$DailyActivityTableAnnotationComposer,
      $$DailyActivityTableCreateCompanionBuilder,
      $$DailyActivityTableUpdateCompanionBuilder,
      (
        DailyActivityData,
        BaseReferences<_$AppDatabase, $DailyActivityTable, DailyActivityData>,
      ),
      DailyActivityData,
      PrefetchHooks Function()
    >;
typedef $$ReviewQueueRowsTableCreateCompanionBuilder =
    ReviewQueueRowsCompanion Function({
      required String id,
      required String termId,
      required String exerciseMode,
      required String response,
      required int clientSeq,
      required String answeredAt,
      Value<bool> usedHint,
      Value<bool> isPractice,
      Value<int?> latencyMs,
      Value<String?> sessionId,
      Value<int?> ladderStep,
      Value<int> rowid,
    });
typedef $$ReviewQueueRowsTableUpdateCompanionBuilder =
    ReviewQueueRowsCompanion Function({
      Value<String> id,
      Value<String> termId,
      Value<String> exerciseMode,
      Value<String> response,
      Value<int> clientSeq,
      Value<String> answeredAt,
      Value<bool> usedHint,
      Value<bool> isPractice,
      Value<int?> latencyMs,
      Value<String?> sessionId,
      Value<int?> ladderStep,
      Value<int> rowid,
    });

class $$ReviewQueueRowsTableFilterComposer
    extends Composer<_$AppDatabase, $ReviewQueueRowsTable> {
  $$ReviewQueueRowsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get exerciseMode => $composableBuilder(
    column: $table.exerciseMode,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get response => $composableBuilder(
    column: $table.response,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get clientSeq => $composableBuilder(
    column: $table.clientSeq,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get answeredAt => $composableBuilder(
    column: $table.answeredAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get usedHint => $composableBuilder(
    column: $table.usedHint,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get isPractice => $composableBuilder(
    column: $table.isPractice,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get latencyMs => $composableBuilder(
    column: $table.latencyMs,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sessionId => $composableBuilder(
    column: $table.sessionId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ladderStep => $composableBuilder(
    column: $table.ladderStep,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ReviewQueueRowsTableOrderingComposer
    extends Composer<_$AppDatabase, $ReviewQueueRowsTable> {
  $$ReviewQueueRowsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get exerciseMode => $composableBuilder(
    column: $table.exerciseMode,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get response => $composableBuilder(
    column: $table.response,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get clientSeq => $composableBuilder(
    column: $table.clientSeq,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get answeredAt => $composableBuilder(
    column: $table.answeredAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get usedHint => $composableBuilder(
    column: $table.usedHint,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get isPractice => $composableBuilder(
    column: $table.isPractice,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get latencyMs => $composableBuilder(
    column: $table.latencyMs,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sessionId => $composableBuilder(
    column: $table.sessionId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ladderStep => $composableBuilder(
    column: $table.ladderStep,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ReviewQueueRowsTableAnnotationComposer
    extends Composer<_$AppDatabase, $ReviewQueueRowsTable> {
  $$ReviewQueueRowsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get termId =>
      $composableBuilder(column: $table.termId, builder: (column) => column);

  GeneratedColumn<String> get exerciseMode => $composableBuilder(
    column: $table.exerciseMode,
    builder: (column) => column,
  );

  GeneratedColumn<String> get response =>
      $composableBuilder(column: $table.response, builder: (column) => column);

  GeneratedColumn<int> get clientSeq =>
      $composableBuilder(column: $table.clientSeq, builder: (column) => column);

  GeneratedColumn<String> get answeredAt => $composableBuilder(
    column: $table.answeredAt,
    builder: (column) => column,
  );

  GeneratedColumn<bool> get usedHint =>
      $composableBuilder(column: $table.usedHint, builder: (column) => column);

  GeneratedColumn<bool> get isPractice => $composableBuilder(
    column: $table.isPractice,
    builder: (column) => column,
  );

  GeneratedColumn<int> get latencyMs =>
      $composableBuilder(column: $table.latencyMs, builder: (column) => column);

  GeneratedColumn<String> get sessionId =>
      $composableBuilder(column: $table.sessionId, builder: (column) => column);

  GeneratedColumn<int> get ladderStep => $composableBuilder(
    column: $table.ladderStep,
    builder: (column) => column,
  );
}

class $$ReviewQueueRowsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $ReviewQueueRowsTable,
          ReviewQueueRow,
          $$ReviewQueueRowsTableFilterComposer,
          $$ReviewQueueRowsTableOrderingComposer,
          $$ReviewQueueRowsTableAnnotationComposer,
          $$ReviewQueueRowsTableCreateCompanionBuilder,
          $$ReviewQueueRowsTableUpdateCompanionBuilder,
          (
            ReviewQueueRow,
            BaseReferences<
              _$AppDatabase,
              $ReviewQueueRowsTable,
              ReviewQueueRow
            >,
          ),
          ReviewQueueRow,
          PrefetchHooks Function()
        > {
  $$ReviewQueueRowsTableTableManager(
    _$AppDatabase db,
    $ReviewQueueRowsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ReviewQueueRowsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ReviewQueueRowsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ReviewQueueRowsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> id = const Value.absent(),
                Value<String> termId = const Value.absent(),
                Value<String> exerciseMode = const Value.absent(),
                Value<String> response = const Value.absent(),
                Value<int> clientSeq = const Value.absent(),
                Value<String> answeredAt = const Value.absent(),
                Value<bool> usedHint = const Value.absent(),
                Value<bool> isPractice = const Value.absent(),
                Value<int?> latencyMs = const Value.absent(),
                Value<String?> sessionId = const Value.absent(),
                Value<int?> ladderStep = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => ReviewQueueRowsCompanion(
                id: id,
                termId: termId,
                exerciseMode: exerciseMode,
                response: response,
                clientSeq: clientSeq,
                answeredAt: answeredAt,
                usedHint: usedHint,
                isPractice: isPractice,
                latencyMs: latencyMs,
                sessionId: sessionId,
                ladderStep: ladderStep,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String id,
                required String termId,
                required String exerciseMode,
                required String response,
                required int clientSeq,
                required String answeredAt,
                Value<bool> usedHint = const Value.absent(),
                Value<bool> isPractice = const Value.absent(),
                Value<int?> latencyMs = const Value.absent(),
                Value<String?> sessionId = const Value.absent(),
                Value<int?> ladderStep = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => ReviewQueueRowsCompanion.insert(
                id: id,
                termId: termId,
                exerciseMode: exerciseMode,
                response: response,
                clientSeq: clientSeq,
                answeredAt: answeredAt,
                usedHint: usedHint,
                isPractice: isPractice,
                latencyMs: latencyMs,
                sessionId: sessionId,
                ladderStep: ladderStep,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ReviewQueueRowsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $ReviewQueueRowsTable,
      ReviewQueueRow,
      $$ReviewQueueRowsTableFilterComposer,
      $$ReviewQueueRowsTableOrderingComposer,
      $$ReviewQueueRowsTableAnnotationComposer,
      $$ReviewQueueRowsTableCreateCompanionBuilder,
      $$ReviewQueueRowsTableUpdateCompanionBuilder,
      (
        ReviewQueueRow,
        BaseReferences<_$AppDatabase, $ReviewQueueRowsTable, ReviewQueueRow>,
      ),
      ReviewQueueRow,
      PrefetchHooks Function()
    >;
typedef $$ExposureQueueRowsTableCreateCompanionBuilder =
    ExposureQueueRowsCompanion Function({
      required String termId,
      required String shownAt,
      Value<String?> sessionId,
      Value<int> rowid,
    });
typedef $$ExposureQueueRowsTableUpdateCompanionBuilder =
    ExposureQueueRowsCompanion Function({
      Value<String> termId,
      Value<String> shownAt,
      Value<String?> sessionId,
      Value<int> rowid,
    });

class $$ExposureQueueRowsTableFilterComposer
    extends Composer<_$AppDatabase, $ExposureQueueRowsTable> {
  $$ExposureQueueRowsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get shownAt => $composableBuilder(
    column: $table.shownAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get sessionId => $composableBuilder(
    column: $table.sessionId,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ExposureQueueRowsTableOrderingComposer
    extends Composer<_$AppDatabase, $ExposureQueueRowsTable> {
  $$ExposureQueueRowsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get termId => $composableBuilder(
    column: $table.termId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get shownAt => $composableBuilder(
    column: $table.shownAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get sessionId => $composableBuilder(
    column: $table.sessionId,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ExposureQueueRowsTableAnnotationComposer
    extends Composer<_$AppDatabase, $ExposureQueueRowsTable> {
  $$ExposureQueueRowsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get termId =>
      $composableBuilder(column: $table.termId, builder: (column) => column);

  GeneratedColumn<String> get shownAt =>
      $composableBuilder(column: $table.shownAt, builder: (column) => column);

  GeneratedColumn<String> get sessionId =>
      $composableBuilder(column: $table.sessionId, builder: (column) => column);
}

class $$ExposureQueueRowsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $ExposureQueueRowsTable,
          ExposureQueueRow,
          $$ExposureQueueRowsTableFilterComposer,
          $$ExposureQueueRowsTableOrderingComposer,
          $$ExposureQueueRowsTableAnnotationComposer,
          $$ExposureQueueRowsTableCreateCompanionBuilder,
          $$ExposureQueueRowsTableUpdateCompanionBuilder,
          (
            ExposureQueueRow,
            BaseReferences<
              _$AppDatabase,
              $ExposureQueueRowsTable,
              ExposureQueueRow
            >,
          ),
          ExposureQueueRow,
          PrefetchHooks Function()
        > {
  $$ExposureQueueRowsTableTableManager(
    _$AppDatabase db,
    $ExposureQueueRowsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ExposureQueueRowsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ExposureQueueRowsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ExposureQueueRowsTableAnnotationComposer(
                $db: db,
                $table: table,
              ),
          updateCompanionCallback:
              ({
                Value<String> termId = const Value.absent(),
                Value<String> shownAt = const Value.absent(),
                Value<String?> sessionId = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => ExposureQueueRowsCompanion(
                termId: termId,
                shownAt: shownAt,
                sessionId: sessionId,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String termId,
                required String shownAt,
                Value<String?> sessionId = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => ExposureQueueRowsCompanion.insert(
                termId: termId,
                shownAt: shownAt,
                sessionId: sessionId,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ExposureQueueRowsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $ExposureQueueRowsTable,
      ExposureQueueRow,
      $$ExposureQueueRowsTableFilterComposer,
      $$ExposureQueueRowsTableOrderingComposer,
      $$ExposureQueueRowsTableAnnotationComposer,
      $$ExposureQueueRowsTableCreateCompanionBuilder,
      $$ExposureQueueRowsTableUpdateCompanionBuilder,
      (
        ExposureQueueRow,
        BaseReferences<
          _$AppDatabase,
          $ExposureQueueRowsTable,
          ExposureQueueRow
        >,
      ),
      ExposureQueueRow,
      PrefetchHooks Function()
    >;
typedef $$CachedImagesTableCreateCompanionBuilder =
    CachedImagesCompanion Function({
      required String url,
      required String file,
      required int bytes,
      required DateTime usedAt,
      Value<int> rowid,
    });
typedef $$CachedImagesTableUpdateCompanionBuilder =
    CachedImagesCompanion Function({
      Value<String> url,
      Value<String> file,
      Value<int> bytes,
      Value<DateTime> usedAt,
      Value<int> rowid,
    });

class $$CachedImagesTableFilterComposer
    extends Composer<_$AppDatabase, $CachedImagesTable> {
  $$CachedImagesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get url => $composableBuilder(
    column: $table.url,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get file => $composableBuilder(
    column: $table.file,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get bytes => $composableBuilder(
    column: $table.bytes,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get usedAt => $composableBuilder(
    column: $table.usedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedImagesTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedImagesTable> {
  $$CachedImagesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get url => $composableBuilder(
    column: $table.url,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get file => $composableBuilder(
    column: $table.file,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get bytes => $composableBuilder(
    column: $table.bytes,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get usedAt => $composableBuilder(
    column: $table.usedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedImagesTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedImagesTable> {
  $$CachedImagesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get url =>
      $composableBuilder(column: $table.url, builder: (column) => column);

  GeneratedColumn<String> get file =>
      $composableBuilder(column: $table.file, builder: (column) => column);

  GeneratedColumn<int> get bytes =>
      $composableBuilder(column: $table.bytes, builder: (column) => column);

  GeneratedColumn<DateTime> get usedAt =>
      $composableBuilder(column: $table.usedAt, builder: (column) => column);
}

class $$CachedImagesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedImagesTable,
          CachedImage,
          $$CachedImagesTableFilterComposer,
          $$CachedImagesTableOrderingComposer,
          $$CachedImagesTableAnnotationComposer,
          $$CachedImagesTableCreateCompanionBuilder,
          $$CachedImagesTableUpdateCompanionBuilder,
          (
            CachedImage,
            BaseReferences<_$AppDatabase, $CachedImagesTable, CachedImage>,
          ),
          CachedImage,
          PrefetchHooks Function()
        > {
  $$CachedImagesTableTableManager(_$AppDatabase db, $CachedImagesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedImagesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedImagesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedImagesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> url = const Value.absent(),
                Value<String> file = const Value.absent(),
                Value<int> bytes = const Value.absent(),
                Value<DateTime> usedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedImagesCompanion(
                url: url,
                file: file,
                bytes: bytes,
                usedAt: usedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String url,
                required String file,
                required int bytes,
                required DateTime usedAt,
                Value<int> rowid = const Value.absent(),
              }) => CachedImagesCompanion.insert(
                url: url,
                file: file,
                bytes: bytes,
                usedAt: usedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedImagesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedImagesTable,
      CachedImage,
      $$CachedImagesTableFilterComposer,
      $$CachedImagesTableOrderingComposer,
      $$CachedImagesTableAnnotationComposer,
      $$CachedImagesTableCreateCompanionBuilder,
      $$CachedImagesTableUpdateCompanionBuilder,
      (
        CachedImage,
        BaseReferences<_$AppDatabase, $CachedImagesTable, CachedImage>,
      ),
      CachedImage,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$CollectionsTableTableManager get collections =>
      $$CollectionsTableTableManager(_db, _db.collections);
  $$CollectionItemsTableTableManager get collectionItems =>
      $$CollectionItemsTableTableManager(_db, _db.collectionItems);
  $$TermsTableTableManager get terms =>
      $$TermsTableTableManager(_db, _db.terms);
  $$TermProgressTableTableManager get termProgress =>
      $$TermProgressTableTableManager(_db, _db.termProgress);
  $$SyncMetaTableTableManager get syncMeta =>
      $$SyncMetaTableTableManager(_db, _db.syncMeta);
  $$TriagedTermsTableTableManager get triagedTerms =>
      $$TriagedTermsTableTableManager(_db, _db.triagedTerms);
  $$PendingGenerationsTableTableManager get pendingGenerations =>
      $$PendingGenerationsTableTableManager(_db, _db.pendingGenerations);
  $$DailyActivityTableTableManager get dailyActivity =>
      $$DailyActivityTableTableManager(_db, _db.dailyActivity);
  $$ReviewQueueRowsTableTableManager get reviewQueueRows =>
      $$ReviewQueueRowsTableTableManager(_db, _db.reviewQueueRows);
  $$ExposureQueueRowsTableTableManager get exposureQueueRows =>
      $$ExposureQueueRowsTableTableManager(_db, _db.exposureQueueRows);
  $$CachedImagesTableTableManager get cachedImages =>
      $$CachedImagesTableTableManager(_db, _db.cachedImages);
}
