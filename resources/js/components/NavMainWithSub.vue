<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '@/components/ui/sidebar'
import { useCurrentUrl } from '@/composables/useCurrentUrl'
import type { NavGroup } from '@/types'

defineProps<{
  items: NavGroup[]
}>()

const { isCurrentUrl } = useCurrentUrl()
</script>

<template>
  <SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>Platform</SidebarGroupLabel>
    <SidebarMenu>
      <template v-for="item in items" :key="item.title">
        <!-- Flat item (no children) -->
        <SidebarMenuItem v-if="!item.children || item.children.length === 0">
          <SidebarMenuButton
            as-child
            :is-active="item.href ? isCurrentUrl(item.href) : false"
            :tooltip="item.title"
          >
            <Link :href="item.href ?? '#'">
              <component :is="item.icon" v-if="item.icon" />
              <span>{{ item.title }}</span>
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>

        <!-- Collapsible group (with children) -->
        <Collapsible v-else as-child default-open class="group/collapsible">
          <SidebarMenuItem>
            <CollapsibleTrigger as-child>
              <SidebarMenuButton :tooltip="item.title">
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ item.title }}</span>
                <ChevronRight
                  class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                />
              </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <SidebarMenuSub>
                <template v-for="child in item.children" :key="child.title">
                  <SidebarMenuSubItem>
                    <SidebarMenuSubButton
                      as-child
                      :is-active="isCurrentUrl(child.href)"
                    >
                      <Link :href="child.href">
                        <span>{{ child.title }}</span>
                      </Link>
                    </SidebarMenuSubButton>
                  </SidebarMenuSubItem>
                </template>
              </SidebarMenuSub>
            </CollapsibleContent>
          </SidebarMenuItem>
        </Collapsible>
      </template>
    </SidebarMenu>
  </SidebarGroup>
</template>
