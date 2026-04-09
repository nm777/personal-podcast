import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface MediaFile {
    id: number;
    file_path: string;
    file_hash: string;
    mime_type: string;
    filesize: number;
    duration?: number;
    public_url?: string;
    source_url?: string;
    created_at: string;
    updated_at: string;
}

export interface LibraryItem {
    id: number;
    user_id: number;
    media_file_id?: number;
    title: string;
    description?: string;
    source_type: string;
    source_url?: string;
    is_duplicate: boolean;
    duplicate_detected_at?: string;
    processing_status: string;
    processing_started_at?: string;
    processing_completed_at?: string;
    processing_error?: string;
    created_at: string;
    updated_at: string;
    media_file?: MediaFile | null;
    feeds?: Feed[];
}

export interface FeedItem {
    id: number;
    feed_id: number;
    library_item_id: number;
    sequence: number;
    library_item: LibraryItem;
}

export interface Feed {
    id: number;
    title: string;
    description?: string;
    is_public: boolean;
    slug: string;
    user_guid: string;
    token?: string;
    items_count?: number;
    items?: FeedItem[];
    created_at: string;
    updated_at: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    feeds: Feed[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    is_admin: boolean;
    approval_status: 'pending' | 'approved' | 'rejected';
    created_at: string;
    updated_at: string;
}
