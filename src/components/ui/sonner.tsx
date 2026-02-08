import { useTheme } from "next-themes";
import { Toaster as Sonner, toast } from "sonner";

type ToasterProps = React.ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme();

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      position="bottom-right"
      richColors={false}
      closeButton
      toastOptions={{
        classNames: {
          // Base toast — uses --toast-* CSS tokens so it adapts to light/dark
          toast:
            "group toast group-[.toaster]:!bg-[hsl(var(--toast-bg))] group-[.toaster]:!text-[hsl(var(--toast-fg))] group-[.toaster]:!border group-[.toaster]:!border-[hsl(var(--toast-border))] group-[.toaster]:!rounded-xl group-[.toaster]:!p-4",
          description:
            "group-[.toast]:!text-[hsl(var(--toast-desc))] group-[.toast]:!text-sm",
          actionButton:
            "group-[.toast]:!bg-primary group-[.toast]:!text-primary-foreground group-[.toast]:!rounded-lg group-[.toast]:!font-medium group-[.toast]:!px-3 group-[.toast]:!py-1.5",
          cancelButton:
            "group-[.toast]:!bg-transparent group-[.toast]:!border group-[.toast]:!border-[hsl(var(--toast-border))] group-[.toast]:!text-[hsl(var(--toast-desc))] group-[.toast]:!rounded-lg",
          closeButton:
            "group-[.toast]:!bg-[hsl(var(--destructive))] group-[.toast]:!border-[hsl(var(--destructive))] group-[.toast]:!text-[hsl(var(--destructive-foreground))] hover:group-[.toast]:!opacity-90 group-[.toast]:!rounded-full group-[.toast]:!w-5 group-[.toast]:!h-5 group-[.toast]:!-right-1 group-[.toast]:!-top-1 group-[.toast]:!shadow-md",
          // Semantic type overrides
          success:
            "group-[.toaster]:!bg-[hsl(var(--toast-success-bg))] group-[.toaster]:!border-[hsl(var(--toast-success-border))] group-[.toaster]:!text-[hsl(var(--toast-success-fg))]",
          error:
            "group-[.toaster]:!bg-[hsl(var(--toast-error-bg))] group-[.toaster]:!border-[hsl(var(--toast-error-border))] group-[.toaster]:!text-[hsl(var(--toast-error-fg))]",
          warning:
            "group-[.toaster]:!bg-[hsl(var(--toast-warning-bg))] group-[.toaster]:!border-[hsl(var(--toast-warning-border))] group-[.toaster]:!text-[hsl(var(--toast-warning-fg))]",
          info:
            "group-[.toaster]:!bg-[hsl(var(--toast-info-bg))] group-[.toaster]:!border-[hsl(var(--toast-info-border))] group-[.toaster]:!text-[hsl(var(--toast-info-fg))]",
          title:
            "group-[.toast]:!text-base group-[.toast]:!font-semibold",
        },
        style: {
          // Use the theme-aware shadow token
          boxShadow: "var(--toast-shadow)",
        },
      }}
      {...props}
    />
  );
};

export { Toaster, toast };
