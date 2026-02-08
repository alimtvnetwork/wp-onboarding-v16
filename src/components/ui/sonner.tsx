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
      duration={4000}
      gap={8}
      toastOptions={{
        classNames: {
          // Base toast — dark neutral surface, Poppins, consistent radius and padding
          toast:
            "group toast group-[.toaster]:!bg-[hsl(var(--toast-bg))] group-[.toaster]:!text-[hsl(var(--toast-fg))] group-[.toaster]:!border group-[.toaster]:!border-[hsl(var(--toast-border))] group-[.toaster]:!rounded-xl group-[.toaster]:!px-4 group-[.toaster]:!py-3",
          // Muted description text
          description:
            "group-[.toast]:!text-[hsl(var(--toast-desc))] group-[.toast]:!text-sm",
          // Action button — secondary style, inverts on hover
          actionButton:
            "group-[.toast]:!bg-white group-[.toast]:!text-[hsl(var(--destructive))] group-[.toast]:!border group-[.toast]:!border-[hsl(var(--destructive)/0.3)] group-[.toast]:!rounded-lg group-[.toast]:!font-medium group-[.toast]:!px-3 group-[.toast]:!py-1.5 hover:group-[.toast]:!bg-[hsl(var(--destructive))] hover:group-[.toast]:!text-white hover:group-[.toast]:!border-[hsl(var(--destructive))]",
          // Cancel button
          cancelButton:
            "group-[.toast]:!bg-transparent group-[.toast]:!border group-[.toast]:!border-[hsl(var(--toast-border))] group-[.toast]:!text-[hsl(var(--toast-desc))] group-[.toast]:!rounded-lg",
          // Close (×) — top-right, destructive red, compact circle
          closeButton:
            "group-[.toast]:!bg-[hsl(var(--muted))] group-[.toast]:!border-[hsl(var(--toast-border))] group-[.toast]:!text-[hsl(var(--muted-foreground))] hover:group-[.toast]:!bg-[hsl(var(--muted-foreground)/0.2)] group-[.toast]:!rounded-full group-[.toast]:!w-5 group-[.toast]:!h-5 group-[.toast]:!-right-1.5 group-[.toast]:!-top-1.5 group-[.toast]:!left-auto group-[.toast]:!shadow-sm",
          // Semantic type overrides — only bg/border/text change, structure stays identical
          success:
            "group-[.toaster]:!bg-[hsl(var(--toast-success-bg))] group-[.toaster]:!border-[hsl(var(--toast-success-border))] group-[.toaster]:!text-[hsl(var(--toast-success-fg))]",
          error:
            "group-[.toaster]:!bg-[hsl(var(--toast-error-bg))] group-[.toaster]:!border-[hsl(var(--toast-error-border))] group-[.toaster]:!text-[hsl(var(--toast-error-fg))]",
          warning:
            "group-[.toaster]:!bg-[hsl(var(--toast-warning-bg))] group-[.toaster]:!border-[hsl(var(--toast-warning-border))] group-[.toaster]:!text-[hsl(var(--toast-warning-fg))]",
          info:
            "group-[.toaster]:!bg-[hsl(var(--toast-info-bg))] group-[.toaster]:!border-[hsl(var(--toast-info-border))] group-[.toaster]:!text-[hsl(var(--toast-info-fg))]",
          // Title — semibold, slightly larger
          title:
            "group-[.toast]:!text-sm group-[.toast]:!font-semibold",
        },
        style: {
          fontFamily: "'Poppins', system-ui, sans-serif",
          boxShadow: "var(--toast-shadow)",
        },
      }}
      {...props}
    />
  );
};

export { Toaster, toast };
