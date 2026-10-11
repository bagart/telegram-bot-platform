import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Bot,
    Building2,
    FolderGit2,
    LayoutGrid,
    Puzzle,
    ShieldAlert,
} from 'lucide-react';
import type { NavItem } from '@/types';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { dashboard as antispamDashboard } from '@/routes/antispam';
import { index as modulesActivation } from '@/routes/management/modules';
import { index as superadminBots } from '@/routes/superadmin/bots';
import { index as superadminWorkspaces } from '@/routes/superadmin/workspaces';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Anti-Spam',
        href: antispamDashboard(),
        icon: ShieldAlert,
    },
    // Owner-scoped surface: the page lists only bots the session user owns
    // (workspace-owner resolution server-side) and shows an empty state
    // otherwise — no shared flag exists to filter on in the sidebar.
    {
        title: 'Modules',
        href: modulesActivation(),
        icon: Puzzle,
    },
    // Both entries are superadmin-only at runtime: the routes answer 403 for
    // every other session, and the sidebar has no shared superadmin flag to
    // filter on (HandleInertiaRequests is host-owned).
    {
        title: 'Workspaces',
        href: superadminWorkspaces(),
        icon: Building2,
    },
    {
        title: 'Bot catalog',
        href: superadminBots(),
        icon: Bot,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
