export function HeadingSmall({ title, description }: { title: string; description?: string }) {
    return (
        <header>
            <h3 className="text-base font-medium">{title}</h3>
            {description && <p className="text-muted-foreground mt-0.5 text-sm">{description}</p>}
        </header>
    );
}