export default function FluidBackground({ lively = false }) {
    return (
        <div className="pointer-events-none absolute inset-0" aria-hidden="true">
            <span className="auth-fluid-bg__blob auth-fluid-bg__blob--1" />
            <span className="auth-fluid-bg__blob auth-fluid-bg__blob--2" />
            <span className="auth-fluid-bg__blob auth-fluid-bg__blob--3" />
            <span className="auth-fluid-bg__blob auth-fluid-bg__blob--4" />
            {lively && <span className="auth-fluid-bg__blob auth-fluid-bg__blob--5" />}
            <span className="auth-fluid-bg__building" />
            <span className="auth-fluid-bg__sheen" />
        </div>
    );
}
