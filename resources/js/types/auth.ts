export type User = {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    is_admin: boolean;
    credits: number;
    unlimited: boolean;
    ai_active: boolean;
};

export type Auth = {
    user: User | null;
};
