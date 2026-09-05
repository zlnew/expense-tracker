<script setup lang="ts" generic="TRow">
import type { HTMLAttributes } from 'vue'

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
    class="hidden overflow-x-auto rounded-2xl border border-[#1f222e] bg-[#0a0a0c] shadow-xl md:block"
    :class="props.class"
  >
    <table class="w-full border-collapse text-left">
      <thead class="sticky top-0 z-10 border-b border-[#1f222e] bg-[#121217]">
        <tr>
          <th
            v-for="(col, i) in props.columns"
            :key="i"
            class="px-4 py-3 font-mono text-[11px] font-semibold tracking-wider text-zinc-400 uppercase"
            :class="col.headerClass"
          >
            {{ col.header }}
          </th>
        </tr>
      </thead>
      <tbody
        class="divide-y divide-[#1f222e]/60 font-mono text-xs text-zinc-200"
      >
        <tr
          v-for="(row, i) in props.rows"
          :key="i"
          class="transition-colors hover:bg-[#121217]/60"
        >
          <td
            v-for="(col, j) in props.columns"
            :key="j"
            class="px-4 py-3.5 align-middle"
            :class="col.cellClass"
          >
            <slot :name="`cell-${j}`" :row="row" :index="i">
              {{ col.cell(row, i) }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Mobile: cards (<md) -->
  <div class="grid grid-cols-1 gap-3 md:hidden">
    <div
      v-for="(row, i) in props.rows"
      :key="i"
      class="rounded-2xl border border-[#1f222e] bg-[#0a0a0c] p-4 text-zinc-100 shadow-lg"
    >
      <slot name="card" :row="row" :index="i">
        <div
          v-for="(col, j) in props.columns"
          :key="j"
          class="mb-1 font-mono text-xs"
        >
          <slot :name="`cell-${j}`" :row="row" :index="i">
            {{ col.cell(row, i) }}
          </slot>
        </div>
      </slot>
    </div>
  </div>
</template>
