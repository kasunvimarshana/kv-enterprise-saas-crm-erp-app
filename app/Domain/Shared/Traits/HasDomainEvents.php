<?php

namespace App\Domain\Shared\Traits;

trait HasDomainEvents
{
    private array $domainEvents = [];
    
    protected function raise(object $event): void
    {
        $this->domainEvents[] = $event;
    }
    
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        
        return $events;
    }
    
    public function clearDomainEvents(): void
    {
        $this->domainEvents = [];
    }
    
    public function getDomainEvents(): array
    {
        return $this->domainEvents;
    }
}
