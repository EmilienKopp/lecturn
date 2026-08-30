import type { LinkComponentBaseProps } from '@inertiajs/core';
import type { Component, SvelteComponent } from 'svelte';

type NavIcon =
    | Component<{ class?: string }>
    | (new (...args: any[]) => SvelteComponent<{ class?: string }>);

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<LinkComponentBaseProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<LinkComponentBaseProps['href']>;
    icon?: NavIcon;
    isActive?: boolean;
};

/** A node of the server-built navigation tree (spatie/laravel-navigation). */
export type ServerNavItem = {
    url: string;
    title: string;
    active: boolean;
    attributes: { icon?: string };
    children: ServerNavItem[];
    depth: number;
};
