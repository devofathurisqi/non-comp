<?php
declare(strict_types=1);

function chemnama_module_catalog(): array
{
    return [
        1 => ['badge' => 'Modul 1', 'title' => 'Tata Nama Senyawa ION', 'description' => 'Tata nama senyawa ion dan ion poliatomik.', 'accent' => '#10b981', 'icon' => 'flask', 'sort_order' => 1],
        2 => ['badge' => 'Modul 2', 'title' => 'Tata Nama Senyawa Molekular', 'description' => 'Tata nama senyawa molekular dan kovalen.', 'accent' => '#f59e0b', 'icon' => 'atom', 'sort_order' => 2],
        3 => ['badge' => 'Modul 3', 'title' => 'Tata Nama Senyawa Asam dan Basa', 'description' => 'Tata nama senyawa asam dan basa.', 'accent' => '#ec4899', 'icon' => 'droplet', 'sort_order' => 3],
    ];
}

function chemnama_module_number(string $title): int
{
    $titles = [
        'tata nama senyawa ion' => 1,
        'konsep dasar senyawa' => 1,
        'senyawa poliatomik' => 1,
        'senyawa ion & kovalen' => 1,
        'tata nama senyawa molekular' => 2,
        'tata nama senyawa biner' => 2,
        'tata nama senyawa asam dan basa' => 3,
        'tata nama asam' => 3,
        'tata nama basa' => 3,
    ];
    $key = strtolower(trim((string) preg_replace('/\s+/', ' ', $title)));
    if (!isset($titles[$key])) {
        throw new RuntimeException('Modul tidak dikenal; data dipertahankan: ' . $title);
    }
    return $titles[$key];
}

/** Include every module_id column so linked content is moved before duplicates are removed. */
function chemnama_module_reference_tables(PDO $pdo): array
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return $pdo->query("SELECT TABLE_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND COLUMN_NAME = 'module_id' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    }
    $tables = [];
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) continue;
        foreach ($pdo->query('PRAGMA table_info(`' . $table . '`)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if ($column['name'] === 'module_id') $tables[] = $table;
        }
    }
    return $tables;
}

/** Merge the old six-module catalog and its duplicate seed without deleting learning content. */
function chemnama_normalize_modules(PDO $pdo): void
{
    $catalog = chemnama_module_catalog();
    $read = 'SELECT * FROM modules ORDER BY sort_order, id';
    $rows = $pdo->query($read)->fetchAll(PDO::FETCH_ASSOC);
    $matches = count($rows) === count($catalog);
    foreach (array_values($rows) as $index => $row) {
        $expected = $catalog[$index + 1] ?? null;
        if (!$expected || $row['badge'] !== $expected['badge'] || $row['title'] !== $expected['title'] || (int) $row['sort_order'] !== $expected['sort_order']) $matches = false;
    }
    if ($matches) return;

    $pdo->beginTransaction();
    try {
        // Lock and re-read: concurrent requests must use the catalog committed by the first request.
        $rows = $pdo->query($read . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
        $groups = [1 => [], 2 => [], 3 => []];
        foreach ($rows as $row) $groups[chemnama_module_number($row['title'])][] = $row;
        $tables = chemnama_module_reference_tables($pdo);
        foreach ($catalog as $number => $definition) {
            $group = $groups[$number];
            // Retain an existing canonical row, otherwise the oldest row in this group.
            usort($group, static function (array $left, array $right) use ($definition): int {
                return (($left['title'] === $definition['title'] ? 0 : 1) <=> ($right['title'] === $definition['title'] ? 0 : 1)) ?: ((int) $left['id'] <=> (int) $right['id']);
            });
            if (!$group) {
                $insert = $pdo->prepare('INSERT INTO modules (badge, title, description, accent, icon, sort_order) VALUES (:badge, :title, :description, :accent, :icon, :sort_order)');
                $insert->execute($definition);
                continue;
            }
            $target = (int) $group[0]['id'];
            $update = $pdo->prepare('UPDATE modules SET badge = ?, title = ?, description = ?, sort_order = ? WHERE id = ?');
            $update->execute([$definition['badge'], $definition['title'], $definition['description'], $definition['sort_order'], $target]);
            foreach (array_slice($group, 1) as $duplicate) {
                $source = (int) $duplicate['id'];
                foreach ($tables as $table) {
                    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) throw new RuntimeException('Invalid module reference table');
                    $pdo->prepare('UPDATE `' . $table . '` SET module_id = ? WHERE module_id = ?')->execute([$target, $source]);
                }
                $pdo->prepare('DELETE FROM modules WHERE id = ?')->execute([$source]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function chemnama_catalog_module_id(PDO $pdo, int $number): int
{
    $statement = $pdo->prepare('SELECT id FROM modules WHERE badge = ? ORDER BY id LIMIT 1');
    $statement->execute(['Modul ' . $number]);
    $id = (int) $statement->fetchColumn();
    if (!$id) throw new RuntimeException('Modul tidak tersedia.');
    return $id;
}
