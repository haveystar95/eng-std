<script setup lang="ts" generic="T">
// Paper-style table: hairline row rules, quiet header, optional clickable rows.
// Columns are declared once; a cell defaults to row[key] but is normally overridden
// with a `#cell-<key>` slot. Wide content scrolls inside its own container.
export interface Column {
  key: string
  label: string
  align?: 'left' | 'right' | 'center'
  tnum?: boolean
  width?: string
}

defineProps<{
  columns: Column[]
  rows: T[]
  rowKey: (row: T) => string
  clickable?: boolean
}>()
defineEmits<{ rowClick: [row: T] }>()
defineSlots<Record<`cell-${string}`, (props: { row: T }) => unknown>>()

// Fallback cell content when a column has no `#cell-<key>` slot.
function cellText(row: T, key: string): unknown {
  return (row as Record<string, unknown>)[key]
}
</script>

<template>
  <div class="table-wrap">
    <table class="ptable">
      <thead>
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            :style="{ textAlign: col.align ?? 'left', width: col.width }"
          >
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="rowKey(row)"
          :class="{ clickable }"
          @click="clickable && $emit('rowClick', row)"
        >
          <td
            v-for="col in columns"
            :key="col.key"
            :class="{ tnum: col.tnum }"
            :style="{ textAlign: col.align ?? 'left' }"
          >
            <slot :name="`cell-${col.key}`" :row="row">{{ cellText(row, col.key) }}</slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table-wrap {
  overflow-x: auto;
}
.ptable {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
thead th {
  text-align: left;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--secondary);
  border-bottom: 1px solid var(--hairline);
  white-space: nowrap;
}
tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid var(--divider-faint);
  vertical-align: middle;
  color: var(--ink);
}
tbody tr:last-child td {
  border-bottom: none;
}
tbody tr.clickable {
  cursor: pointer;
}
tbody tr.clickable:hover td {
  background: var(--faint-ink);
}
.tnum {
  font-variant-numeric: tabular-nums;
}
</style>
