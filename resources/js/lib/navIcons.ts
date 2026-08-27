import LayoutGrid from 'lucide-svelte/icons/layout-grid';
import Presentation from 'lucide-svelte/icons/presentation';
import User from 'lucide-svelte/icons/user';
import Users from 'lucide-svelte/icons/users';
import type { NavItem, ServerNavItem } from '@/types';

const icons: Record<string, NavItem['icon']> = {
    'layout-grid': LayoutGrid,
    presentation: Presentation,
    user: User,
    users: Users,
};

export function toNavItem(item: ServerNavItem): NavItem {
    return {
        title: item.title,
        href: item.url,
        icon: item.attributes.icon ? icons[item.attributes.icon] : undefined,
        isActive: item.active,
    };
}
