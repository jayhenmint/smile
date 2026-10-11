import { Smile } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="bg-primary text-primary-foreground flex aspect-square size-9 items-center justify-center rounded-xl">
                <Smile className="size-6" aria-hidden="true" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate text-xl leading-none font-medium tracking-wide">SMILE</span>
            </div>
        </>
    );
}
