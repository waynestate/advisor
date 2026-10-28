<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Module;

/**
 * A bundle of tools, resources and prompts. Each stack project can ship one
 * (PROCTOR's tools in PROCTOR's repo, and so on); ADVISOR loads it only if the
 * manifest names it. A module class has a constructor taking ModuleContext.
 */
interface Module
{
    /** A short id, used in logs and the audit trail. */
    public function id(): string;

    /**
     * @return list<ToolDefinition>
     */
    public function tools(): array;

    /**
     * @return list<ResourceDefinition>
     */
    public function resources(): array;

    /**
     * @return list<PromptDefinition>
     */
    public function prompts(): array;
}
