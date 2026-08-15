<script setup lang="ts" generic="TRow">
import type { HTMLAttributes } from 'vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

type Column = {
  /** i18n key or plain string for the desktop header. */
  header?: string
  /** Cell renderer: receives the row and its index. */
  cell: (row: TRow, index: number) => unknown
  /** Optional class for the header cell. */
  headerClass?: string
  /** Optional class for every body cell in this column. */
  cellClass?: string
}

type Props = {
  columns: Column[]
  rows: TRow[]
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <!-- Desktop: table (md+) -->
  <div
    class="hidden overflow-x-clip rounded-md border bg-background md:block"
    :class="props.class"
  >
    <Table class="w-full">
      <TableHeader class="sticky top-0 z-10 bg-background">
        <TableRow>
          <TableHead
            v-for="(col, i) in props.columns"
            :key="i"
            :class="col.headerClass"
          >
            {{ col.header }}
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow v-for="(row, i) in props.rows" :key="i">
          <TableCell
            v-for="(col, j) in props.columns"
            :key="j"
            :class="col.cellClass"
          >
            <slot :name="`cell-${j}`" :row="row" :index="i">
              {{ col.cell(row, i) }}
            </slot>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>

  <!-- Mobile: cards (<md) -->
  <div class="grid grid-cols-1 gap-4 md:hidden">
    <div
      v-for="(row, i) in props.rows"
      :key="i"
      class="rounded-lg border bg-background p-4 shadow-sm"
    >
      <slot name="card" :row="row" :index="i">
        <div v-for="(col, j) in props.columns" :key="j" class="mb-1">
          <slot :name="`cell-${j}`" :row="row" :index="i">
            {{ col.cell(row, i) }}
          </slot>
        </div>
      </slot>
    </div>
  </div>
</template>
