import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpenCheck, BrainCircuit, FileText, History, LayoutDashboard, MessageSquareText, Route } from 'lucide-react';

const logo = new URL('../../assets/logo.png', import.meta.url).href;

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'AI Essay Review',
        url: '/dashboard?section=essay-review',
        icon: FileText,
    },
    {
        title: 'Rubric Coach',
        url: '/dashboard?section=rubric-coach',
        icon: BookOpenCheck,
    },
    {
        title: 'Personal Plan',
        url: '/dashboard?section=personal-plan',
        icon: Route,
    },
    {
        title: 'Feedback Chat',
        url: '/dashboard?section=feedback-chat',
        icon: MessageSquareText,
    },
    {
        title: 'Progress Insights',
        url: '/dashboard?section=progress-insights',
        icon: BrainCircuit,
    },
    {
        title: 'Review History',
        url: '/dashboard?section=history',
        icon: History,
    },
];

/*const footerNavItems: NavItem[] = [
    {
        title: 'AI Feedback Guide',
        url: '/dashboard?section=guide',
        icon: Sparkles,
    },
];*/

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <img src={logo} alt="InstaFeedback AI dashboard" className="mb-1 size-20 rounded-md object-contain" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" /> */}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
